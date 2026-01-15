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
use App\Service\Planning\PlanningConflictService;
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
        private PlanningConflictService $conflictService,
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
     * Get conflict details for an affectation
     * GET /api/planning-assignment/{id}/conflict-details
     * IMPORTANT: This route must come BEFORE /{year}/{month} to avoid conflicts
     */
    #[Route('/{id}/conflict-details', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getConflictDetails(int $id): JsonResponse
    {
        try {
            $affectation = $this->affectationRepository->find($id);

            if (!$affectation) {
                return $this->json([
                    'error' => 'Affectation non trouvée',
                    'id' => $id
                ], 404);
            }
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération de l\'affectation',
                'message' => $e->getMessage()
            ], 500);
        }

        $statut = $affectation->getStatut();

        // If no conflict, return null
        if (!in_array($statut, [Affectation::STATUS_TO_REPLACE_ABSENCE, Affectation::STATUS_TO_REPLACE_RDV])) {
            return $this->json(['reason' => null]);
        }

        $user = $affectation->getUser();
        $userName = $user?->getFullName() ?? 'Éducateur';

        // Log affectation details for debugging
        error_log(sprintf(
            '[CONFLICT DEBUG] Affectation #%d: User=%s (%d), Status=%s, Start=%s, End=%s',
            $affectation->getId(),
            $userName,
            $user?->getId() ?? 0,
            $statut,
            $affectation->getStartAt()?->format('Y-m-d H:i:s') ?? 'NULL',
            $affectation->getEndAt()?->format('Y-m-d H:i:s') ?? 'NULL'
        ));

        // Absence conflict
        if ($statut === Affectation::STATUS_TO_REPLACE_ABSENCE) {
            error_log('[CONFLICT DEBUG] Searching for absence conflict...');

            // First, get ALL approved absences for this user to see what's available
            $allAbsences = $this->absenceRepository->createQueryBuilder('a')
                ->leftJoin('a.absenceType', 'at')
                ->where('a.user = :user')
                ->andWhere('a.status = :status')
                ->setParameter('user', $user)
                ->setParameter('status', Absence::STATUS_APPROVED)
                ->getQuery()
                ->getResult();

            error_log(sprintf(
                '[CONFLICT DEBUG] Found %d approved absences for user %s',
                count($allAbsences),
                $userName
            ));

            foreach ($allAbsences as $abs) {
                error_log(sprintf(
                    '[CONFLICT DEBUG] - Absence #%d: Type=%s, Start=%s, End=%s',
                    $abs->getId(),
                    $abs->getAbsenceType()?->getLabel() ?? 'NULL',
                    $abs->getStartAt()?->format('Y-m-d H:i:s') ?? 'NULL',
                    $abs->getEndAt()?->format('Y-m-d H:i:s') ?? 'NULL'
                ));
            }

            // Now search for overlapping absence
            $absence = $this->absenceRepository->createQueryBuilder('a')
                ->where('a.user = :user')
                ->andWhere('a.status = :status')
                ->andWhere('a.startAt <= :end')
                ->andWhere('a.endAt >= :start')
                ->setParameter('user', $user)
                ->setParameter('status', Absence::STATUS_APPROVED)
                ->setParameter('start', $affectation->getStartAt())
                ->setParameter('end', $affectation->getEndAt())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            error_log(sprintf(
                '[CONFLICT DEBUG] Overlapping absence query result: %s',
                $absence ? 'FOUND (ID: ' . $absence->getId() . ')' : 'NOT FOUND'
            ));

            if ($absence) {
                $absenceType = $absence->getAbsenceType()?->getLabel() ?? 'Absence';
                $startDate = $absence->getStartAt()?->format('d/m/Y') ?? '?';
                $endDate = $absence->getEndAt()?->format('d/m/Y') ?? '?';

                $reason = sprintf(
                    '%s ne peut pas assurer cette garde. %s du %s au %s.',
                    $userName,
                    $absenceType,
                    $startDate,
                    $endDate
                );

                return $this->json([
                    'reason' => $reason,
                    'type' => 'absence',
                    'absenceId' => $absence->getId(),
                    'absenceType' => $absenceType,
                    'startDate' => $startDate,
                    'endDate' => $endDate
                ]);
            }
        }

        // RDV conflict
        if ($statut === Affectation::STATUS_TO_REPLACE_RDV) {
            error_log('[CONFLICT DEBUG] Searching for RDV conflict...');

            // First, get ALL RDVs for this user to see what's available
            $allRdvs = $this->rendezVousRepository->createQueryBuilder('r')
                ->join('r.participants', 'p')
                ->where('p.id = :userId')
                ->andWhere('r.impactGarde = :impact')
                ->andWhere('r.statut != :cancelled')
                ->setParameter('userId', $user->getId())
                ->setParameter('impact', true)
                ->setParameter('cancelled', RendezVous::STATUS_CANCELLED)
                ->getQuery()
                ->getResult();

            error_log(sprintf(
                '[CONFLICT DEBUG] Found %d RDVs with impactGarde=true for user %s',
                count($allRdvs),
                $userName
            ));

            foreach ($allRdvs as $rdvItem) {
                error_log(sprintf(
                    '[CONFLICT DEBUG] - RDV #%d: Title=%s, Start=%s, End=%s, Status=%s',
                    $rdvItem->getId(),
                    $rdvItem->getTitle() ?? 'NULL',
                    $rdvItem->getStartAt()?->format('Y-m-d H:i:s') ?? 'NULL',
                    $rdvItem->getEndAt()?->format('Y-m-d H:i:s') ?? 'NULL',
                    $rdvItem->getStatut() ?? 'NULL'
                ));
            }

            $rdv = $this->rendezVousRepository->createQueryBuilder('r')
                ->join('r.participants', 'p')
                ->where('p.id = :userId')
                ->andWhere('r.impactGarde = :impact')
                ->andWhere('r.startAt <= :end')
                ->andWhere('r.endAt >= :start')
                ->andWhere('r.statut != :cancelled')
                ->setParameter('userId', $user->getId())
                ->setParameter('impact', true)
                ->setParameter('start', $affectation->getStartAt())
                ->setParameter('end', $affectation->getEndAt())
                ->setParameter('cancelled', RendezVous::STATUS_CANCELLED)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            error_log(sprintf(
                '[CONFLICT DEBUG] Overlapping RDV query result: %s',
                $rdv ? 'FOUND (ID: ' . $rdv->getId() . ')' : 'NOT FOUND'
            ));

            if ($rdv) {
                $rdvTitle = $rdv->getTitle() ?? 'Rendez-vous';
                $rdvDate = $rdv->getStartAt()?->format('d/m/Y à H:i') ?? '?';

                $reason = sprintf(
                    '%s ne peut pas assurer cette garde. Rendez-vous prévu: "%s" le %s.',
                    $userName,
                    $rdvTitle,
                    $rdvDate
                );

                return $this->json([
                    'reason' => $reason,
                    'type' => 'rdv',
                    'rdvId' => $rdv->getId(),
                    'rdvTitle' => $rdvTitle,
                    'rdvDate' => $rdvDate
                ]);
            }
        }

        // Fallback if conflict detected but details not found
        error_log('[CONFLICT DEBUG] Fallback: No matching absence or RDV found despite conflict status');

        return $this->json([
            'reason' => sprintf(
                '%s ne peut pas assurer cette garde. Conflit détecté.',
                $userName
            ),
            'type' => 'unknown',
            'debug' => [
                'affectationId' => $affectation->getId(),
                'userId' => $user?->getId(),
                'userName' => $userName,
                'status' => $statut,
                'affectationStart' => $affectation->getStartAt()?->format('Y-m-d H:i:s'),
                'affectationEnd' => $affectation->getEndAt()?->format('Y-m-d H:i:s'),
                'note' => 'Affectation marked as conflicting but no matching absence/RDV found. Check logs for details.'
            ]
        ]);
    }

    /**
     * Get all plannings for a specific month (all villas)
     * GET /api/planning-assignment/{year}/{month}
     */
    #[Route('/{year}/{month}', methods: ['GET'], requirements: ['year' => '\d+', 'month' => '\d+'])]
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
                    'commentaire' => $affectation->getCommentaire(),
                    'isSegmented' => $affectation->getIsSegmented(),
                    'segmentNumber' => $affectation->getSegmentNumber(),
                    'totalSegments' => $affectation->getTotalSegments()
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
     * Get all month data in one request (consolidated endpoint)
     * GET /api/planning-assignment/month-data/{year}/{month}
     *
     * Returns: {
     *   plannings: [...],
     *   absences: [...],
     *   rendezvous: [...],
     *   astreintes: [...]
     * }
     */
    #[Route('/month-data/{year}/{month}', methods: ['GET'])]
    public function getMonthData(int $year, int $month, Request $request): JsonResponse
    {
        // Validate month
        if ($month < 1 || $month > 12) {
            return $this->json(['error' => 'Invalid month'], 400);
        }

        // Get month boundaries (extend to include adjacent months for overlap)
        $startDate = new \DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        // 1. Get plannings with affectations
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

        $planningsData = [];
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
                    'commentaire' => $affectation->getCommentaire(),
                    'isSegmented' => $affectation->getIsSegmented(),
                    'segmentNumber' => $affectation->getSegmentNumber(),
                    'totalSegments' => $affectation->getTotalSegments()
                ];
            }

            $planningsData[] = [
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

        // 2. Get absences (approved only, overlapping the month)
        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->leftJoin('a.absenceType', 't')
            ->addSelect('u', 't')
            ->where('a.status = :approved')
            ->andWhere('a.startAt <= :monthEnd')
            ->andWhere('a.endAt >= :monthStart')
            ->setParameter('approved', 'APPROVED')
            ->setParameter('monthStart', $startDate->format('Y-m-d'))
            ->setParameter('monthEnd', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $absencesData = [];
        foreach ($absences as $absence) {
            $absencesData[] = [
                'id' => $absence->getId(),
                'user' => [
                    'id' => $absence->getUser()->getId(),
                    'fullName' => $absence->getUser()->getFullName()
                ],
                'absenceType' => [
                    'code' => $absence->getAbsenceType()->getCode(),
                    'label' => $absence->getAbsenceType()->getLabel()
                ],
                'startAt' => $absence->getStartAt()->format('Y-m-d'),
                'endAt' => $absence->getEndAt()->format('Y-m-d')
            ];
        }

        // 3. Get rendez-vous (with impactGarde=true, overlapping the month)
        $rdvs = $this->rendezVousRepository->createQueryBuilder('r')
            ->leftJoin('r.appointmentParticipants', 'ap')
            ->leftJoin('ap.user', 'u')
            ->addSelect('ap', 'u')
            ->where('r.impactGarde = true')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.startAt <= :monthEnd')
            ->andWhere('r.endAt >= :monthStart')
            ->setParameter('statuses', ['CONFIRME', 'EN_ATTENTE'])
            ->setParameter('monthStart', $startDate->format('Y-m-d H:i:s'))
            ->setParameter('monthEnd', $endDate->format('Y-m-d H:i:s'))
            ->getQuery()
            ->getResult();

        $rdvsData = [];
        foreach ($rdvs as $rdv) {
            $participants = [];
            foreach ($rdv->getAppointmentParticipants() as $participant) {
                $participants[] = [
                    'id' => $participant->getUser()->getId(),
                    'fullName' => $participant->getUser()->getFullName()
                ];
            }

            $rdvsData[] = [
                'id' => $rdv->getId(),
                'title' => $rdv->getTitre(),
                'startAt' => $rdv->getStartAt()->format('c'),
                'endAt' => $rdv->getEndAt()->format('c'),
                'participants' => $participants
            ];
        }

        // 4. Get astreintes (overlapping the month)
        $astreintes = $this->astreinteRepository->createQueryBuilder('a')
            ->where('a.startAt <= :endDate')
            ->andWhere('a.endAt >= :startDate')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $astreintesData = [];
        foreach ($astreintes as $astreinte) {
            $educateur = $astreinte->getEducateur();
            if (!$educateur) {
                continue; // Skip unassigned astreintes
            }

            $endDate = (clone $astreinte->getEndAt())->modify('+1 day');

            $astreintesData[] = [
                'id' => $astreinte->getId(),
                'startAt' => $astreinte->getStartAt()->format('Y-m-d'),
                'endAt' => $endDate->format('Y-m-d'),
                'periodLabel' => $astreinte->getPeriodLabel(),
                'status' => $astreinte->getStatus(),
                'educateur' => [
                    'id' => $educateur->getId(),
                    'fullName' => $educateur->getFullName(),
                    'color' => $educateur->getColor()
                ]
            ];
        }

        // Prepare response data
        $responseData = [
            'plannings' => $planningsData,
            'absences' => $absencesData,
            'rendezvous' => $rdvsData,
            'astreintes' => $astreintesData
        ];

        // Generate ETag based on content
        $etag = md5(json_encode($responseData));

        // Create response with cache headers
        $response = $this->json($responseData);

        // Set cache headers (max-age: 60s, must-revalidate)
        $response->setCache([
            'max_age' => 60,
            'must_revalidate' => true,
            'public' => false,  // Private cache (user-specific data)
        ]);

        // Set ETag for conditional requests
        $response->setEtag($etag);

        // Check if client has fresh cache (If-None-Match header)
        if ($response->isNotModified($request)) {
            // Return 304 Not Modified (no body)
            return $response;
        }

        return $response;
    }

    /**
     * Create a new affectation
     * POST /api/planning-assignment/create
     */
    #[Route('/create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $villaId = $data['villaId'] ?? null;
        $type = $data['type'] ?? null;
        $startAt = $data['startAt'] ?? null;
        $endAt = $data['endAt'] ?? null;
        $warnings = [];

        // Type is always required
        if (!$type || !$startAt || !$endAt) {
            return $this->json(['error' => 'Missing required parameters (type, startAt, endAt)'], 400);
        }

        // Villa obligatoire seulement pour gardes principales
        if ($type !== Affectation::TYPE_RENFORT && !$villaId) {
            return $this->json(['error' => 'Villa requise pour les gardes principales'], 400);
        }

        // Renfort: autoriser villa (renfort villa-spécifique) ou null (centre-complet)
        if ($type === Affectation::TYPE_RENFORT && $villaId) {
            $warnings[] = 'Renfort villa-spécifique créé. Laisser villa vide pour un renfort centre-complet.';
        }

        // Find villa if provided
        $villa = null;
        if ($villaId) {
            $villa = $this->villaRepository->find($villaId);
            if (!$villa) {
                return $this->json(['error' => 'Villa not found'], 404);
            }
        }

        // Parse dates
        try {
            $start = new \DateTime($startAt);
            $end = new \DateTime($endAt);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        // Extract month and year from start date
        $month = (int)$start->format('n');
        $year = (int)$start->format('Y');

        // Find or create PlanningMonth
        // Pour les renforts sans villa, utiliser le premier planning disponible ou en créer un générique
        if ($villa) {
            $planning = $this->planningRepository->findOneBy([
                'villa' => $villa,
                'annee' => $year,
                'mois' => $month
            ]);

            if (!$planning) {
                // Create new PlanningMonth for this villa
                $planning = new \App\Entity\PlanningMonth();
                $planning->setVilla($villa);
                $planning->setAnnee($year);
                $planning->setMois($month);
                $planning->setStatut('draft');
                $this->em->persist($planning);
            }
        } else {
            // Renfort sans villa: utiliser le premier planning disponible du mois
            // ou créer un planning avec la première villa disponible (contrainte technique)
            $planning = $this->planningRepository->findOneBy([
                'annee' => $year,
                'mois' => $month
            ]);

            if (!$planning) {
                // Créer un planning avec la première villa (contrainte technique PlanningMonth)
                $firstVilla = $this->villaRepository->findOneBy([]);
                if (!$firstVilla) {
                    return $this->json(['error' => 'Aucune villa disponible'], 500);
                }
                $planning = new \App\Entity\PlanningMonth();
                $planning->setVilla($firstVilla);
                $planning->setAnnee($year);
                $planning->setMois($month);
                $planning->setStatut('draft');
                $this->em->persist($planning);
            }
        }

        // Create new affectation
        $affectation = new Affectation();
        $affectation->setPlanningMois($planning);
        $affectation->setVilla($villa); // Peut être null pour renfort centre-complet
        $affectation->setType($type);
        $affectation->setStartAt($start);
        $affectation->setEndAt($end);
        $affectation->setStatut($data['statut'] ?? Affectation::STATUS_DRAFT);
        $affectation->setCommentaire($data['commentaire'] ?? null);

        // Assign user if provided
        $userId = $data['userId'] ?? null;

        if ($userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $affectation->setUser($user);
                // Get assignment warnings and merge with existing warnings
                $assignmentWarnings = $this->assignmentService->getValidationWarnings($affectation);
                $warnings = array_merge($warnings, $assignmentWarnings);
            }
        }

        // Calculate working days
        $workingDays = $this->assignmentService->calculateWorkingDays($affectation);
        $affectation->setJoursTravailes((int)$workingDays);

        $this->em->persist($affectation);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'affectationId' => $affectation->getId(),
            'workingDays' => $workingDays,
            'warnings' => $warnings
        ]);
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
                        // Reset status to draft if validated or to_replace
                        $this->resetStatusIfNeeded($affectation);

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

                        // Vérifier conflits si l'utilisateur ou les dates ont changé
                        if ($affectation->getUser() && (isset($changeData['userId']) || $datesChanged)) {
                            $this->conflictService->checkAndResolveConflicts($affectation);
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

        // Get affectations to validate (draft and conflicts)
        $affectationsToValidate = $this->affectationRepository->createQueryBuilder('a')
            ->innerJoin('a.planningMois', 'p')
            ->where('p.annee = :year')
            ->andWhere('p.mois = :month')
            ->andWhere('a.statut IN (:statuses)')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('statuses', [
                Affectation::STATUS_DRAFT,
                Affectation::STATUS_TO_REPLACE_ABSENCE,
                Affectation::STATUS_TO_REPLACE_RDV,
                Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT
            ])
            ->getQuery()
            ->getResult();

        if (empty($affectationsToValidate)) {
            return $this->json([
                'success' => true,
                'validated' => 0,
                'message' => 'Aucune affectation en brouillon à valider'
            ]);
        }

        // Get ALL affectations for the month (including validated) for schedule conflict detection
        // This ensures we detect conflicts between draft and already validated affectations
        $allAffectations = $this->affectationRepository->createQueryBuilder('a')
            ->innerJoin('a.planningMois', 'p')
            ->where('p.annee = :year')
            ->andWhere('p.mois = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();

        // STEP 1: Check for schedule conflicts (same user on multiple villas at same time)
        $scheduleConflicts = $this->detectScheduleConflicts($allAffectations);

        // Validate affectations (only those to validate, not already validated ones)
        $validated = 0;
        $warnings = [];
        $errors = [];

        foreach ($affectationsToValidate as $affectation) {
            // 1. D'ABORD vérifier le conflit horaire (déjà marqué par detectScheduleConflicts)
            // On vérifie AVANT checkAndResolveConflicts pour ne pas écraser le statut
            if ($affectation->getStatut() === Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT) {
                $errors[] = $this->buildScheduleConflictError($affectation, $scheduleConflicts);
                continue; // Skip validation for this affectation
            }

            // 2. ENSUITE re-vérifier les conflits absences/RDV (uniquement si pas de conflit horaire)
            // This handles cases where absences/RDVs were deleted or modified after marking
            $this->conflictService->checkAndResolveConflicts($affectation);

            // 3. Vérifier absence (BLOCKING)
            if ($affectation->getStatut() === Affectation::STATUS_TO_REPLACE_ABSENCE) {
                $errors[] = $this->buildAbsenceConflictError($affectation);
                continue; // Skip validation for this affectation
            }

            // 4. Vérifier RDV (BLOCKING)
            if ($affectation->getStatut() === Affectation::STATUS_TO_REPLACE_RDV) {
                $errors[] = $this->buildRdvConflictError($affectation);
                continue; // Skip validation for this affectation
            }

            // Check if affectation has a user assigned (WARNING only)
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

            // Validate only if status is DRAFT
            if ($affectation->getStatut() === Affectation::STATUS_DRAFT) {
                $affectation->setStatut(Affectation::STATUS_VALIDATED);
                $validated++;
            }
        }

        // If there are blocking errors, don't save and return error response
        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'validated' => 0,
                'errors' => $errors,
                'warnings' => $warnings,
                'message' => sprintf(
                    'Validation impossible: %d conflit(s) détecté(s). Veuillez réaffecter les gardes concernées.',
                    count($errors)
                )
            ], 400);
        }

        // Save only if no errors
        $this->em->flush();

        return $this->json([
            'success' => true,
            'validated' => $validated,
            'warnings' => $warnings,
            'message' => sprintf('%d affectation(s) validée(s)', $validated)
        ]);
    }

    /**
     * Detect schedule conflicts (same user on multiple affectations at same time)
     * Returns array of conflicting affectation IDs grouped by user
     */
    private function detectScheduleConflicts(array $affectations): array
    {
        $conflicts = [];

        // Group affectations by user
        $byUser = [];
        foreach ($affectations as $aff) {
            if (!$aff->getUser()) continue;

            $userId = $aff->getUser()->getId();
            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [];
            }
            $byUser[$userId][] = $aff;
        }

        // Check for overlaps within each user's affectations
        foreach ($byUser as $userId => $userAffectations) {
            for ($i = 0; $i < count($userAffectations); $i++) {
                for ($j = $i + 1; $j < count($userAffectations); $j++) {
                    $aff1 = $userAffectations[$i];
                    $aff2 = $userAffectations[$j];

                    // Check if they overlap
                    if ($this->affectationsOverlap($aff1, $aff2)) {
                        // Mark both as conflicting
                        $aff1->setStatut(Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT);
                        $aff2->setStatut(Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT);

                        // Store conflict info
                        if (!isset($conflicts[$aff1->getId()])) {
                            $conflicts[$aff1->getId()] = [];
                        }
                        if (!isset($conflicts[$aff2->getId()])) {
                            $conflicts[$aff2->getId()] = [];
                        }

                        $conflicts[$aff1->getId()][] = $aff2;
                        $conflicts[$aff2->getId()][] = $aff1;
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two affectations overlap in time
     */
    private function affectationsOverlap(Affectation $aff1, Affectation $aff2): bool
    {
        $start1 = $aff1->getStartAt();
        $end1 = $aff1->getEndAt();
        $start2 = $aff2->getStartAt();
        $end2 = $aff2->getEndAt();

        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }

        // Two periods overlap if: start1 < end2 AND start2 < end1
        return $start1 < $end2 && $start2 < $end1;
    }

    /**
     * Build detailed error message for schedule conflict
     */
    private function buildScheduleConflictError(Affectation $affectation, array $scheduleConflicts): array
    {
        $userName = $affectation->getUser()?->getFullName() ?? 'Éducateur';
        $villa1Name = $affectation->getVilla()?->getNom() ?? 'Villa inconnue';

        // Get the conflicting affectation(s)
        $conflictingAffectations = $scheduleConflicts[$affectation->getId()] ?? [];

        if (!empty($conflictingAffectations)) {
            $conflictingAff = $conflictingAffectations[0]; // Take first conflict
            $villa2Name = $conflictingAff->getVilla()?->getNom() ?? 'Villa inconnue';

            $message = sprintf(
                '%s ne peut pas assurer cette garde (%s). Déjà affecté(e) simultanément sur %s du %s au %s',
                $userName,
                $villa1Name,
                $villa2Name,
                $conflictingAff->getStartAt()?->format('d/m/Y H:i') ?? '?',
                $conflictingAff->getEndAt()?->format('d/m/Y H:i') ?? '?'
            );
        } else {
            $message = sprintf(
                '%s ne peut pas assurer cette garde (%s). Conflit d\'horaire détecté avec une autre affectation.',
                $userName,
                $villa1Name
            );
        }

        return [
            'type' => 'schedule_conflict',
            'severity' => 'error',
            'message' => $message,
            'affectationId' => $affectation->getId(),
            'userId' => $affectation->getUser()?->getId()
        ];
    }

    /**
     * Build detailed error message for absence conflict
     */
    private function buildAbsenceConflictError(Affectation $affectation): array
    {
        // Retrieve the absence causing the conflict
        $absence = $this->absenceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.endAt >= :start')
            ->setParameter('user', $affectation->getUser())
            ->setParameter('status', Absence::STATUS_APPROVED)
            ->setParameter('start', $affectation->getStartAt())
            ->setParameter('end', $affectation->getEndAt())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $userName = $affectation->getUser()?->getFullName() ?? 'Éducateur';
        $villaName = $affectation->getVilla()?->getNom() ?? 'Villa inconnue';

        if ($absence) {
            $absenceType = $absence->getAbsenceType()?->getLabel() ?? 'Absence';
            $startDate = $absence->getStartAt()?->format('d/m/Y') ?? '?';
            $endDate = $absence->getEndAt()?->format('d/m/Y') ?? '?';

            $message = sprintf(
                '%s ne peut pas assurer cette garde (%s). %s du %s au %s',
                $userName,
                $villaName,
                $absenceType,
                $startDate,
                $endDate
            );
        } else {
            $message = sprintf(
                '%s ne peut pas assurer cette garde (%s). Conflit d\'absence détecté.',
                $userName,
                $villaName
            );
        }

        return [
            'type' => 'absence_conflict',
            'severity' => 'error',
            'message' => $message,
            'affectationId' => $affectation->getId(),
            'userId' => $affectation->getUser()?->getId(),
            'absenceId' => $absence?->getId()
        ];
    }

    /**
     * Build detailed error message for RDV conflict
     */
    private function buildRdvConflictError(Affectation $affectation): array
    {
        // Retrieve the RDV causing the conflict
        $rdv = $this->rendezVousRepository->createQueryBuilder('r')
            ->join('r.participants', 'p')
            ->where('p.id = :userId')
            ->andWhere('r.impactGarde = :impact')
            ->andWhere('r.startAt <= :end')
            ->andWhere('r.endAt >= :start')
            ->andWhere('r.statut != :cancelled')
            ->setParameter('userId', $affectation->getUser()->getId())
            ->setParameter('impact', true)
            ->setParameter('start', $affectation->getStartAt())
            ->setParameter('end', $affectation->getEndAt())
            ->setParameter('cancelled', RendezVous::STATUS_CANCELLED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $userName = $affectation->getUser()?->getFullName() ?? 'Éducateur';
        $villaName = $affectation->getVilla()?->getNom() ?? 'Villa inconnue';

        if ($rdv) {
            $rdvTitle = $rdv->getTitle() ?? 'Rendez-vous';
            $rdvDate = $rdv->getStartAt()?->format('d/m/Y à H:i') ?? '?';

            $message = sprintf(
                '%s ne peut pas assurer cette garde (%s). Rendez-vous prévu: "%s" le %s',
                $userName,
                $villaName,
                $rdvTitle,
                $rdvDate
            );
        } else {
            $message = sprintf(
                '%s ne peut pas assurer cette garde (%s). Conflit de rendez-vous détecté.',
                $userName,
                $villaName
            );
        }

        return [
            'type' => 'rdv_conflict',
            'severity' => 'error',
            'message' => $message,
            'affectationId' => $affectation->getId(),
            'userId' => $affectation->getUser()?->getId(),
            'rdvId' => $rdv?->getId()
        ];
    }

    /**
     * Reset affectation status to draft if it was validated or to_replace
     * This ensures modified affectations are re-validated through monthly validation
     */
    private function resetStatusIfNeeded(Affectation $affectation): void
    {
        $currentStatus = $affectation->getStatut();

        $statusesToReset = [
            Affectation::STATUS_VALIDATED,
            Affectation::STATUS_TO_REPLACE_ABSENCE,
            Affectation::STATUS_TO_REPLACE_RDV,
            Affectation::STATUS_TO_REPLACE_SCHEDULE_CONFLICT
        ];

        if (in_array($currentStatus, $statusesToReset)) {
            $affectation->setStatut(Affectation::STATUS_DRAFT);
        }
    }

}
