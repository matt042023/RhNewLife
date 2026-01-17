<?php

namespace App\Controller\Api;

use App\Entity\Absence;
use App\Entity\Affectation;
use App\Entity\Astreinte;
use App\Entity\JourChome;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Repository\AffectationRepository;
use App\Repository\AstreinteRepository;
use App\Repository\JourChomeRepository;
use App\Repository\RendezVousRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Controller for managing weekly day off (jour chômé) for educators
 */
#[Route('/api/jours-chomes')]
#[IsGranted('ROLE_ADMIN')]
class JourChomeApiController extends AbstractController
{
    public function __construct(
        private JourChomeRepository $jourChomeRepository,
        private UserRepository $userRepository,
        private AffectationRepository $affectationRepository,
        private AbsenceRepository $absenceRepository,
        private RendezVousRepository $rendezVousRepository,
        private AstreinteRepository $astreinteRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get all jours chômés for a specific month
     * GET /api/jours-chomes/{year}/{month}
     */
    #[Route('/{year}/{month}', methods: ['GET'], requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'])]
    public function getByMonth(int $year, int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return $this->json(['error' => 'Invalid month'], 400);
        }

        $joursChomes = $this->jourChomeRepository->findByMonth($year, $month);

        $data = [];
        foreach ($joursChomes as $jourChome) {
            $educateur = $jourChome->getEducateur();
            $data[] = [
                'id' => $jourChome->getId(),
                'date' => $jourChome->getDate()->format('Y-m-d'),
                'notes' => $jourChome->getNotes(),
                'educateur' => [
                    'id' => $educateur->getId(),
                    'fullName' => $educateur->getFullName(),
                    'color' => $educateur->getColor()
                ],
                'weekNumber' => $jourChome->getWeekNumber(),
                'createdAt' => $jourChome->getCreatedAt()?->format('c')
            ];
        }

        return $this->json(['joursChomes' => $data]);
    }

    /**
     * Get full planning for a specific educator (for the modal calendar)
     * Returns plannings, absences, RDVs, astreintes, and jours chômés
     * GET /api/jours-chomes/educateur/{id}/{year}/{month}
     */
    #[Route('/educateur/{id}/{year}/{month}', methods: ['GET'], requirements: ['id' => '\d+', 'year' => '\d{4}', 'month' => '\d{1,2}'])]
    public function getEducateurPlanning(int $id, int $year, int $month): JsonResponse
    {
        if ($month < 1 || $month > 12) {
            return $this->json(['error' => 'Invalid month'], 400);
        }

        $educateur = $this->userRepository->find($id);
        if (!$educateur) {
            return $this->json(['error' => 'Educator not found'], 404);
        }

        // Get month boundaries
        $startDate = new \DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        // 1. Get validated affectations for this educator
        $affectations = $this->affectationRepository->createQueryBuilder('a')
            ->innerJoin('a.planningMois', 'p')
            ->leftJoin('a.villa', 'v')
            ->addSelect('v')
            ->where('a.user = :user')
            ->andWhere('p.annee = :year')
            ->andWhere('p.mois = :month')
            ->andWhere('a.statut = :statut')
            ->setParameter('user', $educateur)
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('statut', Affectation::STATUS_VALIDATED)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $planningsData = [];
        foreach ($affectations as $affectation) {
            $villa = $affectation->getVilla();
            $planningsData[] = [
                'id' => $affectation->getId(),
                'startAt' => $affectation->getStartAt()?->format('c'),
                'endAt' => $affectation->getEndAt()?->format('c'),
                'type' => $affectation->getType(),
                'joursTravailes' => $affectation->getJoursTravailes(),
                'villa' => [
                    'id' => $villa?->getId(),
                    'nom' => $villa?->getNom(),
                    'color' => $villa?->getColor()
                ],
                'statut' => $affectation->getStatut()
            ];
        }

        // 2. Get approved absences
        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->leftJoin('a.absenceType', 't')
            ->addSelect('t')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->andWhere('a.startAt <= :endDate')
            ->andWhere('a.endAt >= :startDate')
            ->setParameter('user', $educateur)
            ->setParameter('status', Absence::STATUS_APPROVED)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $absencesData = [];
        foreach ($absences as $absence) {
            $absencesData[] = [
                'id' => $absence->getId(),
                'absenceType' => [
                    'code' => $absence->getAbsenceType()?->getCode(),
                    'label' => $absence->getAbsenceType()?->getLabel()
                ],
                'startAt' => $absence->getStartAt()?->format('Y-m-d'),
                'endAt' => $absence->getEndAt()?->format('Y-m-d')
            ];
        }

        // 3. Get rendez-vous
        $rdvs = $this->rendezVousRepository->createQueryBuilder('r')
            ->innerJoin('r.appointmentParticipants', 'ap')
            ->where('ap.user = :user')
            ->andWhere('r.startAt <= :endDate')
            ->andWhere('r.endAt >= :startDate')
            ->andWhere('r.statut NOT IN (:excludedStatuses)')
            ->setParameter('user', $educateur)
            ->setParameter('startDate', $startDate->format('Y-m-d H:i:s'))
            ->setParameter('endDate', $endDate->format('Y-m-d H:i:s'))
            ->setParameter('excludedStatuses', [RendezVous::STATUS_CANCELLED, RendezVous::STATUS_ANNULE, RendezVous::STATUS_REFUSE])
            ->getQuery()
            ->getResult();

        $rdvsData = [];
        foreach ($rdvs as $rdv) {
            $rdvsData[] = [
                'id' => $rdv->getId(),
                'title' => $rdv->getTitre(),
                'startAt' => $rdv->getStartAt()->format('c'),
                'endAt' => $rdv->getEndAt()->format('c'),
                'type' => $rdv->getType(),
                'typeLabel' => $rdv->getTypeLabel()
            ];
        }

        // 4. Get astreintes
        $astreintes = $this->astreinteRepository->createQueryBuilder('a')
            ->where('a.educateur = :user')
            ->andWhere('a.startAt <= :endDate')
            ->andWhere('a.endAt >= :startDate')
            ->andWhere('a.status = :status')
            ->setParameter('user', $educateur)
            ->setParameter('startDate', $startDate->format('Y-m-d H:i:s'))
            ->setParameter('endDate', $endDate->format('Y-m-d H:i:s'))
            ->setParameter('status', Astreinte::STATUS_ASSIGNED)
            ->getQuery()
            ->getResult();

        $astreintesData = [];
        foreach ($astreintes as $astreinte) {
            $astreintesData[] = [
                'id' => $astreinte->getId(),
                'startAt' => $astreinte->getStartAt()->format('c'),
                'endAt' => $astreinte->getEndAt()->format('c'),
                'periodLabel' => $astreinte->getPeriodLabel()
            ];
        }

        // 5. Get jours chômés
        $joursChomes = $this->jourChomeRepository->findByEducateurAndMonth($educateur, $year, $month);

        $joursChomesData = [];
        foreach ($joursChomes as $jourChome) {
            $joursChomesData[] = [
                'id' => $jourChome->getId(),
                'date' => $jourChome->getDate()->format('Y-m-d'),
                'notes' => $jourChome->getNotes(),
                'weekNumber' => $jourChome->getWeekNumber()
            ];
        }

        return $this->json([
            'educateur' => [
                'id' => $educateur->getId(),
                'fullName' => $educateur->getFullName(),
                'color' => $educateur->getColor()
            ],
            'plannings' => $planningsData,
            'absences' => $absencesData,
            'rendezvous' => $rdvsData,
            'astreintes' => $astreintesData,
            'joursChomes' => $joursChomesData
        ]);
    }

    /**
     * Create a new jour chômé
     * POST /api/jours-chomes
     * Body: { educateurId: int, date: "YYYY-MM-DD", notes?: string, force?: bool }
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['educateurId']) || !isset($data['date'])) {
            return $this->json(['error' => 'educateurId and date are required'], 400);
        }

        $educateur = $this->userRepository->find($data['educateurId']);
        if (!$educateur) {
            return $this->json(['error' => 'Educator not found'], 404);
        }

        try {
            $date = new \DateTime($data['date']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
        }

        // Check if jour chômé already exists for this educator on this date
        if ($this->jourChomeRepository->existsByEducateurAndDate($educateur, $date)) {
            return $this->json(['error' => 'A jour chômé already exists for this educator on this date'], 409);
        }

        // Check for existing jour chômé in the same week
        $existingInWeek = $this->jourChomeRepository->countByEducateurAndWeek($educateur, $date);
        $force = $data['force'] ?? false;

        if ($existingInWeek > 0 && !$force) {
            // Return warning but don't create
            $existingJours = $this->jourChomeRepository->findByEducateurAndWeek($educateur, $date);
            $existingDates = array_map(fn($jc) => $jc->getDate()->format('Y-m-d'), $existingJours);

            return $this->json([
                'warning' => true,
                'message' => 'Un jour chômé existe déjà cette semaine. Voulez-vous en ajouter un autre ?',
                'existingDates' => $existingDates,
                'requiresForce' => true
            ], 200);
        }

        // Create the jour chômé
        $jourChome = new JourChome();
        $jourChome->setEducateur($educateur);
        $jourChome->setDate($date);
        $jourChome->setNotes($data['notes'] ?? null);

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser) {
            $jourChome->setCreatedBy($currentUser);
        }

        $this->entityManager->persist($jourChome);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $jourChome->getId(),
            'date' => $jourChome->getDate()->format('Y-m-d'),
            'weekNumber' => $jourChome->getWeekNumber(),
            'hadWarning' => $existingInWeek > 0
        ], 201);
    }

    /**
     * Delete a jour chômé
     * DELETE /api/jours-chomes/{id}
     */
    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $jourChome = $this->jourChomeRepository->find($id);

        if (!$jourChome) {
            return $this->json(['error' => 'Jour chômé not found'], 404);
        }

        $date = $jourChome->getDate()->format('Y-m-d');
        $educateurId = $jourChome->getEducateur()->getId();

        $this->entityManager->remove($jourChome);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Jour chômé supprimé',
            'deletedDate' => $date,
            'educateurId' => $educateurId
        ]);
    }
}
