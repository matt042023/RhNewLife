<?php

namespace App\Controller\Api;

use App\Entity\TypeAbsence;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Service\Absence\AbsenceCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/absences')]
#[IsGranted('ROLE_USER')]
class AbsenceApiController extends AbstractController
{
    public function __construct(
        private AbsenceCounterService $counterService,
        private AbsenceRepository $absenceRepository
    ) {
    }

    /**
     * Get user counters for a specific year
     */
    #[Route('/compteurs/{userId}', name: 'api_absence_counters', methods: ['GET'])]
    public function getCounters(int $userId, Request $request): JsonResponse
    {
        // Check if user can access this data
        $currentUser = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles(), true);

        if (!$isAdmin && $currentUser->getId() !== $userId) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $user = $this->getDoctrine()->getRepository(User::class)->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $year = $request->query->getInt('year', (int) date('Y'));
        $counters = $this->counterService->getUserCounters($user, $year);

        $data = [];
        foreach ($counters as $counter) {
            $data[] = [
                'id' => $counter->getId(),
                'type' => [
                    'code' => $counter->getAbsenceType()->getCode(),
                    'label' => $counter->getAbsenceType()->getLabel(),
                ],
                'year' => $counter->getYear(),
                'earned' => $counter->getEarned(),
                'taken' => $counter->getTaken(),
                'remaining' => $counter->getRemaining(),
                'is_negative' => $counter->isNegative(),
            ];
        }

        return $this->json($data);
    }

    /**
     * Calculate working days between two dates
     */
    #[Route('/calculate-working-days', name: 'api_absence_calculate_working_days', methods: ['POST'])]
    public function calculateWorkingDays(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $startDate = isset($data['startDate']) ? new \DateTime($data['startDate']) : null;
        $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : null;

        if (!$startDate || !$endDate) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        if ($endDate < $startDate) {
            return $this->json(['error' => 'La date de fin doit être après la date de début'], 400);
        }

        $workingDays = $this->counterService->calculateWorkingDays($startDate, $endDate);

        return $this->json([
            'workingDays' => $workingDays,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);
    }

    /**
     * Check for overlapping absences
     */
    #[Route('/check-overlap', name: 'api_absence_check_overlap', methods: ['POST'])]
    public function checkOverlap(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Support both camelCase (from JS) and snake_case formats
        $startDate = isset($data['startDate']) ? new \DateTime($data['startDate']) :
                     (isset($data['start_at']) ? new \DateTime($data['start_at']) : null);
        $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) :
                   (isset($data['end_at']) ? new \DateTime($data['end_at']) : null);
        $excludeId = $data['excludeId'] ?? ($data['exclude_id'] ?? null);

        if (!$startDate || !$endDate) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        // Use current user for check
        $currentUser = $this->getUser();

        $qb = $this->absenceRepository->createQueryBuilder('a');
        $qb
            ->where('a.user = :userId')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->lte('a.startAt', ':end'),
                        $qb->expr()->gte('a.endAt', ':start')
                    )
                )
            )
            ->setParameter('userId', $currentUser->getId())
            ->setParameter('statuses', ['pending', 'approved'])
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        $overlaps = $qb->getQuery()->getResult();

        $hasOverlap = count($overlaps) > 0;

        return $this->json([
            'hasOverlap' => $hasOverlap,
            'overlappingAbsence' => $hasOverlap ? [
                'id' => $overlaps[0]->getId(),
                'start' => $overlaps[0]->getStartAt()->format('d/m/Y'),
                'end' => $overlaps[0]->getEndAt()->format('d/m/Y'),
                'type' => $overlaps[0]->getAbsenceType()?->getLabel() ?? $overlaps[0]->getType(),
                'status' => $overlaps[0]->getStatus(),
            ] : null,
        ]);
    }

    /**
     * Check counter balance for requested days
     */
    #[Route('/check-balance', name: 'api_absence_check_balance', methods: ['POST'])]
    public function checkBalance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Support both camelCase (from JS) and snake_case formats
        $typeId = $data['absenceTypeId'] ?? ($data['type_id'] ?? null);
        $workingDays = $data['workingDays'] ?? null;

        if (!$typeId) {
            return $this->json(['error' => 'absenceTypeId est requis'], 400);
        }

        // Use current user
        $currentUser = $this->getUser();
        $absenceType = $this->getDoctrine()->getRepository(TypeAbsence::class)->find($typeId);

        if (!$absenceType) {
            return $this->json(['error' => 'Type d\'absence non trouvé'], 404);
        }

        if (!$absenceType->isDeductFromCounter()) {
            return $this->json([
                'hasCounter' => false,
                'message' => 'Ce type d\'absence ne nécessite pas de vérification de solde',
            ]);
        }

        $year = (int) date('Y');
        $counter = $this->counterService->getOrCreateCounter($currentUser, $absenceType, $year);

        $response = [
            'hasCounter' => true,
            'earned' => $counter->getEarned(),
            'taken' => $counter->getTaken(),
            'remaining' => $counter->getRemaining(),
        ];

        // If workingDays is provided, check if sufficient
        if ($workingDays !== null) {
            $response['workingDays'] = $workingDays;
            $response['hasSufficientBalance'] = $counter->hasSufficientBalance($workingDays);
            $response['deficit'] = max(0, $workingDays - $counter->getRemaining());
        }

        return $this->json($response);
    }

    private function getDoctrine()
    {
        return $this->container->get('doctrine');
    }
}
