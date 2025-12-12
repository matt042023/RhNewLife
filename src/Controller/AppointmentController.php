<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Form\AppointmentRequestType;
use App\Repository\RendezVousRepository;
use App\Repository\AppointmentParticipantRepository;
use App\Service\Appointment\AppointmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rendez-vous')]
#[IsGranted('ROLE_USER')]
class AppointmentController extends AbstractController
{
    public function __construct(
        private AppointmentService $appointmentService,
        private RendezVousRepository $appointmentRepository,
        private AppointmentParticipantRepository $participantRepository
    ) {}

    /**
     * Liste des rendez-vous de l'utilisateur avec onglets
     */
    #[Route('', name: 'app_appointment_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // RDV à venir (confirmés, dans le futur)
        $upcomingAppointments = $this->appointmentRepository->findUpcomingForUser($user);

        // Mes demandes (EN_ATTENTE, REFUSE)
        $myRequests = $this->appointmentRepository->findByParticipant($user, [
            'type' => RendezVous::TYPE_DEMANDE
        ]);

        // Historique (passés ou terminés/annulés)
        $historyAppointments = $this->appointmentRepository->findHistoryForUser($user);

        // Participants en attente de confirmation
        $pendingParticipants = $this->participantRepository->findPendingForUser($user);

        return $this->render('appointment/index.html.twig', [
            'upcomingAppointments' => $upcomingAppointments,
            'myRequests' => $myRequests,
            'historyAppointments' => $historyAppointments,
            'pendingParticipants' => $pendingParticipants,
        ]);
    }

    /**
     * Formulaire de demande de rendez-vous
     */
    #[Route('/demande', name: 'app_appointment_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $appointment = new RendezVous();
        $form = $this->createForm(AppointmentRequestType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $recipient = $form->get('recipient')->getData();
                $preferredDate = $form->get('preferredDate')->getData();

                $this->appointmentService->createRequest(
                    requester: $user,
                    subject: $appointment->getSubject(),
                    recipient: $recipient,
                    preferredDate: $preferredDate,
                    message: $appointment->getDescription()
                );

                $this->addFlash('success', 'Votre demande de rendez-vous a été envoyée avec succès.');

                return $this->redirectToRoute('app_appointment_index', ['tab' => 'requests']);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'envoi de la demande : ' . $e->getMessage());
            }
        }

        return $this->render('appointment/request.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Détails d'un rendez-vous
     */
    #[Route('/{id}', name: 'app_appointment_show', methods: ['GET'])]
    public function show(RendezVous $appointment): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_VIEW', $appointment);

        /** @var User $user */
        $user = $this->getUser();

        // Trouver le participant correspondant à l'utilisateur courant
        $currentParticipant = null;
        foreach ($appointment->getAppointmentParticipants() as $participant) {
            if ($participant->getUser() === $user) {
                $currentParticipant = $participant;
                break;
            }
        }

        return $this->render('appointment/show.html.twig', [
            'appointment' => $appointment,
            'currentParticipant' => $currentParticipant,
        ]);
    }

    /**
     * Confirmer sa présence
     */
    #[Route('/{id}/confirmer-presence', name: 'app_appointment_confirm_presence', methods: ['POST'])]
    public function confirmPresence(RendezVous $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_CONFIRM_PRESENCE', $appointment);

        if (!$this->isCsrfTokenValid('confirm-presence-' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_appointment_show', ['id' => $appointment->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->appointmentService->confirmPresence($appointment, $user);
            $this->addFlash('success', 'Votre présence a été confirmée.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la confirmation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_appointment_show', ['id' => $appointment->getId()]);
    }

    /**
     * Signaler son absence
     */
    #[Route('/{id}/signaler-absence', name: 'app_appointment_signal_absence', methods: ['POST'])]
    public function signalAbsence(RendezVous $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_CONFIRM_PRESENCE', $appointment);

        if (!$this->isCsrfTokenValid('signal-absence-' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_appointment_show', ['id' => $appointment->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->appointmentService->signalAbsence($appointment, $user);
            $this->addFlash('warning', 'Votre absence a été signalée.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du signalement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_appointment_show', ['id' => $appointment->getId()]);
    }
}
