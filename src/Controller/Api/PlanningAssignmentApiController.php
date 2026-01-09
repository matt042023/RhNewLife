<?php

namespace App\Controller\Api;

use App\Entity\Absence;
use App\Entity\Affectation;
use App\Entity\RendezVous;
use App\Entity\SqueletteGarde;
use App\Entity\Villa;
use App\Repository\AbsenceRepository;
use App\Repository\AffectationRepository;
use App\Repository\AstreinteRepository;
use App\Repository\PlanningMonthRepository;
use App\Repository\RendezVousRepository;
use App\Repository\SqueletteGardeRepository;
use App\Repository\UserRepository;
use App\Repository\VillaRepository;
use App\Service\Planning\PlanningAssignmentService;
use App\Service\Planning\PlanningAvailabilityService;
use App\Service\Planning\PlanningValidationService;
use App\Service\Planning\VillaPlanningService;
use App\Service\SqueletteGarde\SqueletteGardeApplicator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/planning-assignment')]
#[IsGranted('ROLE_ADMIN')]
class PlanningAssignmentApiController extends AbstractController
{
    public function __construct(
        private SqueletteGardeRepository $templateRepository,
        private SqueletteGardeApplicator $templateApplicator,
        private PlanningMonthRepository $planningRepository,
        private AffectationRepository $affectationRepository,
        private UserRepository $userRepository,
        private VillaRepository $villaRepository,
        private AbsenceRepository $absenceRepository,
        private RendezVousRepository $rendezVousRepository,
        private AstreinteRepository $astreinteRepository,
        private PlanningAssignmentService $assignmentService,
        private PlanningAvailabilityService $availabilityService,
        private PlanningValidationService $validationService,
        private VillaPlanningService $villaPlanningService,
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Generate skeleton from template for a period
     * POST /api/planning-assignment/generate
     */
    #[Route('/generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate input
        $templateId = $data['templateId'] ?? null;
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $scope = $data['scope'] ?? 'villa'; // 'villa', 'all', 'renfort'
        $villaId = $data['villaId'] ?? null;

        if (!$templateId || !$startDate || !$endDate) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        // Load template
        $template = $this->templateRepository->find($templateId);
        if (!$template) {
            return $this->json(['error' => 'Template not found'], 404);
        }

        // Parse dates
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        // Apply template based on scope
        $villa = null;
        $allVillas = false;
        $renfortOnly = false;

        switch ($scope) {
            case 'villa':
                if (!$villaId) {
                    return $this->json(['error' => 'Villa ID required for scope "villa"'], 400);
                }
                $villa = $this->villaRepository->find($villaId);
                if (!$villa) {
                    return $this->json(['error' => 'Villa not found'], 404);
                }
                break;
            case 'all':
                $allVillas = true;
                break;
            case 'renfort':
                $renfortOnly = true;
                break;
            default:
                return $this->json(['error' => 'Invalid scope'], 400);
        }

        // Apply template
        $result = $this->templateApplicator->applyToPeriod(
            $template,
            $start,
            $end,
            $villa,
            $allVillas,
            $renfortOnly
        );

        return $this->json([
            'success' => true,
            'created' => $result['created'],
            'plannings' => array_map(function ($planning) {
                return [
                    'id' => $planning->getId(),
                    'villa' => $planning->getVilla()->getNom(),
                    'year' => $planning->getAnnee(),
                    'month' => $planning->getMois()
                ];
            }, $result['plannings'])
        ]);
    }

    /**
     * Get all plannings for a specific month (all villas)
     * GET /api/planning-assignment/{year}/{month}
     */
    #[Route('/{year}/{month}', methods: ['GET'])]
    public function getMonth(int $year, int $month): JsonResponse
    {
        // Load all plannings for this month with eager loading
        $plannings = $this->planningRepository->createQueryBuilder('p')
            ->leftJoin('p.affectations', 'a')
            ->leftJoin('a.user', 'u')
            ->leftJoin('p.villa', 'v')
            ->addSelect('a', 'u', 'v')
            ->where('p.annee = :year')
            ->andWhere('p.mois = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($plannings as $planning) {
            $affectations = [];
            foreach ($planning->getAffectations() as $affectation) {
                $user = $affectation->getUser();
                $villa = $affectation->getVilla();

                $affectations[] = [
                    'id' => $affectation->getId(),
                    'startAt' => $affectation->getStartAt()?->format('c'),
                    'endAt' => $affectation->getEndAt()?->format('c'),
                    'type' => $affectation->getType(),
                    'joursTravailes' => $affectation->getJoursTravailes(),
                    'user' => $user ? [
                        'id' => $user->getId(),
                        'fullName' => $user->getFullName(),
                        'color' => $user->getColor()
                    ] : null,
                    'villa' => [
                        'id' => $villa?->getId(),
                        'nom' => $villa?->getNom(),
                        'color' => $villa?->getColor()
                    ],
                    'statut' => $affectation->getStatut(),
                    'commentaire' => $affectation->getCommentaire()
                ];
            }

            $data[] = [
                'id' => $planning->getId(),
                'villa' => [
                    'id' => $planning->getVilla()->getId(),
                    'nom' => $planning->getVilla()->getNom(),
                    'color' => $planning->getVilla()->getColor()
                ],
                'status' => $planning->getStatut(),
                'affectations' => $affectations
            ];
        }

        return $this->json(['plannings' => $data]);
    }

    /**
     * Get all absences for a specific month (for calendar background events)
     * GET /api/planning-assignment/absences/{year}/{month}
     */
    #[Route('/absences/{year}/{month}', methods: ['GET'])]
    public function getAbsencesForMonth(int $year, int $month): JsonResponse
    {
        // Calculate month start and end dates
        $monthStart = new \DateTime("$year-$month-01 00:00:00");
        $monthEnd = (clone $monthStart)->modify('last day of this month')->setTime(23, 59, 59);

        // Fetch all approved absences that overlap with this month
        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->leftJoin('a.absenceType', 't')
            ->addSelect('u', 't')
            ->where('a.status = :approved')
            ->andWhere('a.startAt <= :monthEnd')
            ->andWhere('a.endAt >= :monthStart')
            ->setParameter('approved', Absence::STATUS_APPROVED)
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($absences as $absence) {
            $user = $absence->getUser();
            $absenceType = $absence->getAbsenceType();

            $data[] = [
                'id' => $absence->getId(),
                'user' => [
                    'id' => $user->getId(),
                    'fullName' => $user->getFullName()
                ],
                'absenceType' => [
                    'code' => $absenceType?->getCode() ?? 'AUTRE',
                    'label' => $absenceType?->getLabel() ?? 'Absence'
                ],
                'startAt' => $absence->getStartAt()?->format('c'),
                'endAt' => $absence->getEndAt()?->format('c')
            ];
        }

        return $this->json($data);
    }

    /**
     * Get all rendez-vous for a specific month (for calendar background events)
     * GET /api/planning-assignment/rendezvous/{year}/{month}
     */
    #[Route('/rendezvous/{year}/{month}', methods: ['GET'])]
    public function getRendezVousForMonth(int $year, int $month): JsonResponse
    {
        // Calculate month start and end dates
        $monthStart = new \DateTime("$year-$month-01 00:00:00");
        $monthEnd = (clone $monthStart)->modify('last day of this month')->setTime(23, 59, 59);

        // Fetch all confirmed RDVs affecting shifts that overlap with this month
        $rdvs = $this->rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.appointmentParticipants', 'ap')
            ->leftJoin('ap.user', 'u')
            ->addSelect('ap', 'u')
            ->where('r.impactGarde = true')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.startAt <= :monthEnd')
            ->andWhere('r.endAt >= :monthStart')
            ->setParameter('statuses', [
                RendezVous::STATUS_CONFIRME,
                RendezVous::STATUS_EN_ATTENTE
            ])
            ->setParameter('monthStart', $monthStart)
            ->setParameter('monthEnd', $monthEnd)
            ->orderBy('r.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($rdvs as $rdv) {
            $participants = [];
            foreach ($rdv->getAppointmentParticipants() as $participant) {
                $user = $participant->getUser();
                if ($user) {
                    $participants[] = [
                        'id' => $user->getId(),
                        'fullName' => $user->getFullName()
                    ];
                }
            }

            $data[] = [
                'id' => $rdv->getId(),
                'title' => $rdv->getSubject() ?? $rdv->getTitre(),
                'startAt' => $rdv->getStartAt()?->format('c'),
                'endAt' => $rdv->getEndAt()?->format('c'),
                'participants' => $participants
            ];
        }

        return $this->json($data);
    }

    /**
     * Get astreintes (on-call duties) for a specific month
     * GET /api/planning-assignment/astreintes/{year}/{month}
     */
    #[Route('/astreintes/{year}/{month}', methods: ['GET'])]
    public function getAstreintes(int $year, int $month): JsonResponse
    {
        // Calculate month date range
        $startDate = new \DateTime("$year-$month-01 00:00:00");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        // Fetch astreintes that overlap with the month
        $astreintes = $this->astreinteRepository->createQueryBuilder('a')
            ->where('a.startAt <= :endDate')
            ->andWhere('a.endAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Transform to JSON
        $data = [];
        foreach ($astreintes as $astreinte) {
            $educateur = $astreinte->getEducateur();

            // For FullCalendar background events, end date must be exclusive (next day)
            // If astreinte ends on Sunday 2026-01-11, we send 2026-01-12 so FC displays until 11th included
            $endDate = clone $astreinte->getEndAt();
            $endDate->modify('+1 day');

            $data[] = [
                'id' => $astreinte->getId(),
                'startAt' => $astreinte->getStartAt()->format('Y-m-d'),
                'endAt' => $endDate->format('Y-m-d'),
                'periodLabel' => $astreinte->getPeriodLabel(),
                'status' => $astreinte->getStatus(),
                'educateur' => $educateur ? [
                    'id' => $educateur->getId(),
                    'fullName' => $educateur->getFullName(),
                    'color' => $educateur->getColor() ?? '#3B82F6'
                ] : null
            ];
        }

        return $this->json($data);
    }

    /**
     * Assign user to affectation (drag & drop)
     * POST /api/planning-assignment/assign
     */
    #[Route('/assign', methods: ['POST'])]
    public function assign(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $affectationId = $data['affectationId'] ?? null;
        $userId = $data['userId'] ?? null;

        if (!$affectationId || !$userId) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        $affectation = $this->affectationRepository->find($affectationId);
        if (!$affectation) {
            return $this->json(['error' => 'Affectation not found'], 404);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // Assign user and get warnings
        $warnings = $this->assignmentService->assignUserToAffectation($affectation, $user);

        return $this->json([
            'success' => true,
            'warnings' => $warnings
        ]);
    }

    /**
     * Update affectation hours (drag edges to resize)
     * PUT /api/planning-assignment/hours/{id}
     */
    #[Route('/hours/{id}', methods: ['PUT'])]
    public function updateHours(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $affectation = $this->affectationRepository->find($id);
        if (!$affectation) {
            return $this->json(['error' => 'Affectation not found'], 404);
        }

        $startAt = $data['startAt'] ?? null;
        $endAt = $data['endAt'] ?? null;

        if (!$startAt || !$endAt) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        try {
            $start = new \DateTime($startAt);
            $end = new \DateTime($endAt);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        // Update hours
        $this->assignmentService->updateAffectationHours($affectation, $start, $end);

        // Calculate working days
        $workingDays = $this->assignmentService->calculateWorkingDays($affectation);

        // Get warnings
        $warnings = $this->assignmentService->getValidationWarnings($affectation);

        return $this->json([
            'success' => true,
            'workingDays' => $workingDays,
            'warnings' => $warnings
        ]);
    }

    /**
     * Get user availability for a period
     * GET /api/planning-assignment/availability
     */
    #[Route('/availability', methods: ['GET'])]
    public function getAvailability(Request $request): JsonResponse
    {
        $userId = $request->query->get('userId');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');

        if (!$userId || !$startDate || !$endDate) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $availability = $this->availabilityService->getUserAvailabilityForPeriod($user, $start, $end);

        // Format periods for frontend
        $periods = [];

        foreach ($availability['absences'] as $absence) {
            $periods[] = [
                'start' => $absence['start']->format('c'),
                'end' => $absence['end']->format('c'),
                'type' => $absence['type'],
                'color' => $absence['color'],
                'label' => $absence['label']
            ];
        }

        foreach ($availability['rdvs'] as $rdv) {
            $periods[] = [
                'start' => $rdv['start']->format('c'),
                'end' => $rdv['end']->format('c'),
                'type' => $rdv['type'],
                'color' => $rdv['color'],
                'label' => $rdv['label']
            ];
        }

        return $this->json([
            'userId' => $userId,
            'periods' => $periods
        ]);
    }

    /**
     * Validate planning (check warnings but don't publish)
     * POST /api/planning-assignment/validate
     */
    #[Route('/validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $planningId = $data['planningId'] ?? null;
        if (!$planningId) {
            return $this->json(['error' => 'Missing planningId'], 400);
        }

        $planning = $this->planningRepository->find($planningId);
        if (!$planning) {
            return $this->json(['error' => 'Planning not found'], 404);
        }

        $validationResult = $this->validationService->validatePlanning($planning);

        return $this->json($validationResult->toArray());
    }

    /**
     * Publish planning (final validation with counter updates)
     * POST /api/planning-assignment/publish
     */
    #[Route('/publish', methods: ['POST'])]
    public function publish(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $planningId = $data['planningId'] ?? null;
        if (!$planningId) {
            return $this->json(['error' => 'Missing planningId'], 400);
        }

        $planning = $this->planningRepository->find($planningId);
        if (!$planning) {
            return $this->json(['error' => 'Planning not found'], 404);
        }

        // Publish planning (updates counters)
        $this->villaPlanningService->publishPlanning($planning, $this->getUser());

        return $this->json([
            'success' => true,
            'message' => 'Planning published successfully'
        ]);
    }

    /**
     * Get all users (educators) with availability info
     * GET /api/planning-assignment/users
     */
    #[Route('/users', methods: ['GET'])]
    public function getUsers(): JsonResponse
    {
        // Get all users with ROLE_EDUCATOR or similar
        $users = $this->userRepository->findAll();

        $data = [];
        foreach ($users as $user) {
            // TODO: Calculate remaining days from AnnualDayCounterService
            $remainingDays = 258; // Placeholder

            // TODO: Check if user is currently unavailable
            $unavailable = false;
            $unavailableReason = null;

            $data[] = [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'color' => $user->getColor(),
                'villa' => $user->getVilla() ? [
                    'id' => $user->getVilla()->getId(),
                    'nom' => $user->getVilla()->getNom(),
                    'color' => $user->getVilla()->getColor()
                ] : null,
                'remainingDays' => $remainingDays,
                'unavailable' => $unavailable,
                'unavailableReason' => $unavailableReason
            ];
        }

        return $this->json(['users' => $data]);
    }

    /**
     * Batch update affectations (batch save from frontend)
     * POST /api/planning-assignment/batch-update
     */
    #[Route('/batch-update', methods: ['POST'])]
    public function batchUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $changes = $data['changes'] ?? [];

        if (empty($changes)) {
            return $this->json(['error' => 'No changes provided'], 400);
        }

        $warnings = [];
        $processed = 0;

        foreach ($changes as $change) {
            $affectationId = $change['affectationId'];
            $type = $change['type']; // 'assign', 'update', 'delete'
            $changeData = $change['data'];

            $affectation = $this->affectationRepository->find($affectationId);
            if (!$affectation) {
                $warnings[] = [
                    'type' => 'not_found',
                    'message' => "Affectation #{$affectationId} introuvable",
                    'severity' => 'error'
                ];
                continue;
            }

            try {
                switch ($type) {
                    case 'assign':
                        $userId = $changeData['userId'];
                        $user = $this->userRepository->find($userId);
                        if ($user) {
                            $assignWarnings = $this->assignmentService->assignUserToAffectation($affectation, $user);
                            $warnings = array_merge($warnings, $assignWarnings);
                            $processed++;
                        }
                        break;

                    case 'update':
                        $datesChanged = false;

                        if (isset($changeData['userId'])) {
                            $user = $changeData['userId']
                                ? $this->userRepository->find($changeData['userId'])
                                : null;
                            $affectation->setUser($user);
                        }
                        if (isset($changeData['type'])) {
                            $affectation->setType($changeData['type']);
                        }
                        if (isset($changeData['startAt'])) {
                            $affectation->setStartAt(new \DateTime($changeData['startAt']));
                            $datesChanged = true;
                        }
                        if (isset($changeData['endAt'])) {
                            $affectation->setEndAt(new \DateTime($changeData['endAt']));
                            $datesChanged = true;
                        }
                        if (isset($changeData['commentaire'])) {
                            $affectation->setCommentaire($changeData['commentaire']);
                        }

                        // Recalculer les jours travaillés si les dates ont changé
                        if ($datesChanged) {
                            $workingDays = $this->assignmentService->calculateWorkingDays($affectation);
                            $affectation->setJoursTravailes((int)$workingDays);
                        }

                        $processed++;
                        break;

                    case 'delete':
                        $this->em->remove($affectation);
                        $processed++;
                        break;
                }
            } catch (\Exception $e) {
                $warnings[] = [
                    'type' => 'error',
                    'message' => "Erreur affectation #{$affectationId}: " . $e->getMessage(),
                    'severity' => 'error'
                ];
            }
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'processed' => $processed,
            'warnings' => $warnings
        ]);
    }

    /**
     * Bulk delete draft affectations
     * POST /api/planning-assignment/bulk-delete
     */
    #[Route('/bulk-delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $year = $data['year'];
        $month = $data['month'] ?? null; // null = all months
        $villaId = $data['villaId'] ?? null; // null = all villas

        // Build query - filter on Affectation status, not PlanningMonth status
        $qb = $this->affectationRepository->createQueryBuilder('a')
            ->innerJoin('a.planningMois', 'p')
            ->where('p.annee = :year')
            ->andWhere('a.statut = :draft')
            ->setParameter('year', $year)
            ->setParameter('draft', Affectation::STATUS_DRAFT);

        if ($month) {
            $qb->andWhere('p.mois = :month')
               ->setParameter('month', $month);
        }

        if ($villaId) {
            $qb->andWhere('a.villa = :villa')
               ->setParameter('villa', $villaId);
        }

        $affectations = $qb->getQuery()->getResult();
        $count = count($affectations);

        foreach ($affectations as $affectation) {
            $this->em->remove($affectation);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'deleted' => $count
        ]);
    }

    /**
     * Validate all affectations for a month (change status from draft to validated)
     * POST /api/planning-assignment/validate-month
     */
    #[Route('/validate-month', methods: ['POST'])]
    public function validateMonth(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $year = $data['year'] ?? null;
        $month = $data['month'] ?? null;

        if (!$year || !$month) {
            return $this->json(['error' => 'Missing year or month'], 400);
        }

        // Get all draft affectations for this month
        $affectations = $this->affectationRepository->createQueryBuilder('a')
            ->innerJoin('a.planningMois', 'p')
            ->where('p.annee = :year')
            ->andWhere('p.mois = :month')
            ->andWhere('a.statut = :draft')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('draft', Affectation::STATUS_DRAFT)
            ->getQuery()
            ->getResult();

        if (empty($affectations)) {
            return $this->json([
                'success' => true,
                'validated' => 0,
                'message' => 'Aucune affectation en brouillon à valider'
            ]);
        }

        // Validate all affectations
        $validated = 0;
        $warnings = [];

        foreach ($affectations as $affectation) {
            // Check if affectation has a user assigned
            if (!$affectation->getUser()) {
                $warnings[] = [
                    'type' => 'unassigned',
                    'message' => sprintf(
                        'Garde non affectée: %s du %s au %s',
                        $affectation->getVilla()?->getNom() ?? 'Villa inconnue',
                        $affectation->getStartAt()?->format('d/m/Y H:i'),
                        $affectation->getEndAt()?->format('d/m/Y H:i')
                    ),
                    'severity' => 'warning',
                    'affectationId' => $affectation->getId()
                ];
            }

            // Change status to validated
            $affectation->setStatut(Affectation::STATUS_VALIDATED);
            $validated++;
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'validated' => $validated,
            'warnings' => $warnings,
            'message' => sprintf('%d affectation(s) validée(s)', $validated)
        ]);
    }
}
