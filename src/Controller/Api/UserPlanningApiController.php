<?php

namespace App\Controller\Api;

use App\Entity\Absence;
use App\Entity\Affectation;
use App\Entity\AppointmentParticipant;
use App\Entity\Astreinte;
use App\Entity\RendezVous;
use App\Repository\AbsenceRepository;
use App\Repository\AffectationRepository;
use App\Repository\AppointmentParticipantRepository;
use App\Repository\AstreinteRepository;
use App\Repository\JourChomeRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Controller for user-accessible planning endpoints
 * This controller is accessible by any authenticated user (ROLE_USER)
 */
#[Route('/api/user-planning')]
#[IsGranted('ROLE_USER')]
class UserPlanningApiController extends AbstractController
{
    public function __construct(
        private AffectationRepository $affectationRepository,
        private AbsenceRepository $absenceRepository,
        private RendezVousRepository $rendezVousRepository,
        private AstreinteRepository $astreinteRepository,
        private AppointmentParticipantRepository $appointmentParticipantRepository,
        private JourChomeRepository $jourChomeRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get planning data for the current user (educator dashboard)
     * GET /api/user-planning/my-planning/{year}/{month}
     *
     * Returns only validated affectations for the authenticated user,
     * their approved absences, and their rendez-vous.
     */
    #[Route('/my-planning/{year}/{month}', methods: ['GET'])]
    public function getMyPlanning(int $year, int $month): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        // Validate month
        if ($month < 1 || $month > 12) {
            return $this->json(['error' => 'Invalid month'], 400);
        }

        // Get month boundaries
        $startDate = new \DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        // 1. Get user's validated affectations for this month
        $affectations = $this->affectationRepository->createQueryBuilder('a')
            ->innerJoin('a.planningMois', 'p')
            ->leftJoin('a.villa', 'v')
            ->addSelect('v')
            ->where('a.user = :user')
            ->andWhere('p.annee = :year')
            ->andWhere('p.mois = :month')
            ->andWhere('a.statut = :statut')
            ->setParameter('user', $user)
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
                'user' => [
                    'id' => $user->getId(),
                    'fullName' => $user->getFullName(),
                    'color' => $user->getColor()
                ],
                'villa' => [
                    'id' => $villa?->getId(),
                    'nom' => $villa?->getNom(),
                    'color' => $villa?->getColor()
                ],
                'statut' => $affectation->getStatut(),
                'commentaire' => $affectation->getCommentaire()
            ];
        }

        // 2. Get user's approved absences for this month
        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->andWhere('a.startAt <= :endDate')
            ->andWhere('a.endAt >= :startDate')
            ->setParameter('user', $user)
            ->setParameter('status', Absence::STATUS_APPROVED)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $absencesData = [];
        foreach ($absences as $absence) {
            $absencesData[] = [
                'id' => $absence->getId(),
                'user' => [
                    'id' => $user->getId(),
                    'fullName' => $user->getFullName()
                ],
                'absenceType' => [
                    'code' => $absence->getAbsenceType()?->getCode(),
                    'label' => $absence->getAbsenceType()?->getLabel()
                ],
                'startAt' => $absence->getStartAt()?->format('Y-m-d'),
                'endAt' => $absence->getEndAt()?->format('Y-m-d')
            ];
        }

        // 3. Get user's rendez-vous for this month
        // Use appointmentParticipants (AppointmentParticipant entity) which tracks participation with presence status
        $rdvs = $this->rendezVousRepository->createQueryBuilder('r')
            ->innerJoin('r.appointmentParticipants', 'ap')
            ->where('ap.user = :user')
            ->andWhere('r.startAt <= :endDate')
            ->andWhere('r.endAt >= :startDate')
            ->andWhere('r.statut NOT IN (:excludedStatuses)')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate->format('Y-m-d H:i:s'))
            ->setParameter('endDate', $endDate->format('Y-m-d H:i:s'))
            ->setParameter('excludedStatuses', [RendezVous::STATUS_CANCELLED, RendezVous::STATUS_ANNULE, RendezVous::STATUS_REFUSE])
            ->getQuery()
            ->getResult();

        $rdvsData = [];
        foreach ($rdvs as $rdv) {
            $participants = [];
            $myParticipationStatus = null;

            foreach ($rdv->getAppointmentParticipants() as $appointmentParticipant) {
                $participantUser = $appointmentParticipant->getUser();
                if ($participantUser) {
                    $participants[] = [
                        'id' => $participantUser->getId(),
                        'fullName' => $participantUser->getFullName(),
                        'presenceStatus' => $appointmentParticipant->getPresenceStatus()
                    ];

                    // Track current user's participation status
                    if ($participantUser->getId() === $user->getId()) {
                        $myParticipationStatus = $appointmentParticipant->getPresenceStatus();
                    }
                }
            }

            $rdvsData[] = [
                'id' => $rdv->getId(),
                'title' => $rdv->getTitre(),
                'startAt' => $rdv->getStartAt()->format('c'),
                'endAt' => $rdv->getEndAt()->format('c'),
                'type' => $rdv->getType(),
                'typeLabel' => $rdv->getTypeLabel(),
                'statut' => $rdv->getStatut(),
                'displayStatus' => $rdv->getDisplayStatus(),
                'location' => $rdv->getLocation(),
                'organizer' => $rdv->getOrganizer() ? [
                    'id' => $rdv->getOrganizer()->getId(),
                    'fullName' => $rdv->getOrganizer()->getFullName()
                ] : null,
                'participants' => $participants,
                'myParticipationStatus' => $myParticipationStatus,
                'canConfirm' => $myParticipationStatus === 'PENDING' && !$rdv->isPast()
            ];
        }

        // 4. Get user's astreintes for this month
        $astreintes = $this->astreinteRepository->createQueryBuilder('a')
            ->where('a.educateur = :user')
            ->andWhere('a.startAt <= :endDate')
            ->andWhere('a.endAt >= :startDate')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
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
                'periodLabel' => $astreinte->getPeriodLabel(),
                'status' => $astreinte->getStatus(),
                'educateur' => [
                    'id' => $user->getId(),
                    'fullName' => $user->getFullName(),
                    'color' => $user->getColor()
                ]
            ];
        }

        // 5. Get user's jours chômés (weekly day off) for this month
        $joursChomes = $this->jourChomeRepository->findByEducateurAndMonth($user, $year, $month);

        $joursChomesData = [];
        foreach ($joursChomes as $jourChome) {
            $joursChomesData[] = [
                'id' => $jourChome->getId(),
                'date' => $jourChome->getDate()->format('Y-m-d'),
                'notes' => $jourChome->getNotes()
            ];
        }

        return $this->json([
            'plannings' => $planningsData,
            'absences' => $absencesData,
            'rendezvous' => $rdvsData,
            'astreintes' => $astreintesData,
            'joursChomes' => $joursChomesData
        ]);
    }

    /**
     * Confirm or decline participation in a rendez-vous
     * POST /api/user-planning/rdv/{id}/participation
     *
     * Body: { "action": "confirm" | "decline" }
     */
    #[Route('/rdv/{id}/participation', methods: ['POST'])]
    public function updateParticipation(int $id, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        // Find the rendez-vous
        $rdv = $this->rendezVousRepository->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'Rendez-vous not found'], 404);
        }

        // Check if RDV is past
        if ($rdv->isPast()) {
            return $this->json(['error' => 'Cannot modify participation for past rendez-vous'], 400);
        }

        // Find user's participation
        $participation = $this->appointmentParticipantRepository->findOneBy([
            'appointment' => $rdv,
            'user' => $user
        ]);

        if (!$participation) {
            return $this->json(['error' => 'You are not a participant of this rendez-vous'], 403);
        }

        // Get action from request body
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;

        if (!in_array($action, ['confirm', 'decline'])) {
            return $this->json(['error' => 'Invalid action. Use "confirm" or "decline"'], 400);
        }

        // Update participation status
        if ($action === 'confirm') {
            $participation->confirm();
            $newStatus = AppointmentParticipant::PRESENCE_CONFIRMED;
        } else {
            $participation->markAbsent();
            $newStatus = AppointmentParticipant::PRESENCE_ABSENT;
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $action === 'confirm' ? 'Participation confirmée' : 'Participation déclinée',
            'newStatus' => $newStatus,
            'rdvId' => $id
        ]);
    }

    /**
     * Get details of a specific rendez-vous
     * GET /api/user-planning/rdv/{id}
     */
    #[Route('/rdv/{id}', methods: ['GET'])]
    public function getRdvDetails(int $id): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        // Find the rendez-vous
        $rdv = $this->rendezVousRepository->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'Rendez-vous not found'], 404);
        }

        // Check if user is a participant
        $myParticipation = null;
        $participants = [];

        foreach ($rdv->getAppointmentParticipants() as $appointmentParticipant) {
            $participantUser = $appointmentParticipant->getUser();
            if ($participantUser) {
                $participantData = [
                    'id' => $participantUser->getId(),
                    'fullName' => $participantUser->getFullName(),
                    'presenceStatus' => $appointmentParticipant->getPresenceStatus()
                ];
                $participants[] = $participantData;

                if ($participantUser->getId() === $user->getId()) {
                    $myParticipation = $appointmentParticipant;
                }
            }
        }

        if (!$myParticipation) {
            return $this->json(['error' => 'You are not a participant of this rendez-vous'], 403);
        }

        return $this->json([
            'id' => $rdv->getId(),
            'title' => $rdv->getTitre(),
            'subject' => $rdv->getSubject(),
            'description' => $rdv->getDescription(),
            'startAt' => $rdv->getStartAt()->format('c'),
            'endAt' => $rdv->getEndAt()->format('c'),
            'type' => $rdv->getType(),
            'typeLabel' => $rdv->getTypeLabel(),
            'statut' => $rdv->getStatut(),
            'displayStatus' => $rdv->getDisplayStatus(),
            'displayStatusLabel' => $rdv->getDisplayStatusLabel(),
            'location' => $rdv->getLocation(),
            'organizer' => $rdv->getOrganizer() ? [
                'id' => $rdv->getOrganizer()->getId(),
                'fullName' => $rdv->getOrganizer()->getFullName()
            ] : null,
            'participants' => $participants,
            'myParticipationStatus' => $myParticipation->getPresenceStatus(),
            'canConfirm' => $myParticipation->getPresenceStatus() === AppointmentParticipant::PRESENCE_PENDING && !$rdv->isPast(),
            'isPast' => $rdv->isPast()
        ]);
    }
}
