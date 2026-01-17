<?php

namespace App\Controller\Admin;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\AppointmentParticipant;
use App\Form\AppointmentConvocationType;
use App\Form\AppointmentValidationType;
use App\Form\AppointmentRefusalType;
use App\Form\AppointmentFilterType;
use App\Form\AppointmentMedicalType;
use App\Repository\RendezVousRepository;
use App\Service\Appointment\AppointmentService;
use App\Service\MedicalVisit\MedicalVisitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/rendez-vous')]
#[IsGranted('ROLE_ADMIN')]
class AppointmentController extends AbstractController
{
    public function __construct(
        private AppointmentService $appointmentService,
        private RendezVousRepository $appointmentRepository,
        private MedicalVisitService $medicalVisitService
    ) {}

    /**
     * Liste des rendez-vous avec filtres
     */
    #[Route('', name: 'admin_appointment_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filterForm = $this->createForm(AppointmentFilterType::class);
        $filterForm->handleRequest($request);

        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $data = $filterForm->getData();
            if ($data['status']) {
                $filters['status'] = $data['status'];
            }
            if ($data['type']) {
                $filters['type'] = $data['type'];
            }
            if ($data['dateFrom']) {
                $filters['dateFrom'] = $data['dateFrom'];
            }
            if ($data['dateTo']) {
                $filters['dateTo'] = $data['dateTo'];
            }
            if ($data['participant']) {
                $filters['participant'] = $data['participant'];
            }
        }

        $appointments = $this->appointmentRepository->findAllWithFilters($filters);

        return $this->render('admin/appointment/index.html.twig', [
            'appointments' => $appointments,
            'filterForm' => $filterForm,
        ]);
    }

    /**
     * Dashboard des demandes en attente
     */
    #[Route('/demandes-en-attente', name: 'admin_appointment_pending', methods: ['GET'])]
    public function pending(): Response
    {
        $pendingRequests = $this->appointmentRepository->findPendingRequests();

        return $this->render('admin/appointment/pending.html.twig', [
            'pendingRequests' => $pendingRequests,
        ]);
    }

    /**
     * Créer une convocation
     */
    #[Route('/creer', name: 'admin_appointment_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $appointment = new RendezVous();
        $form = $this->createForm(AppointmentConvocationType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $participants = $form->get('participants')->getData();

                $this->appointmentService->createConvocation(
                    organizer: $user,
                    subject: $appointment->getSubject(),
                    scheduledAt: $appointment->getStartAt(),
                    durationMinutes: $appointment->getDurationMinutes(),
                    participants: $participants->toArray(),
                    description: $appointment->getDescription(),
                    location: $appointment->getLocation()
                );

                $this->addFlash('success', 'La convocation a été créée avec succès.');

                return $this->redirectToRoute('admin_appointment_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }

        return $this->render('admin/appointment/create.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Créer un rendez-vous pour une visite médicale
     */
    #[Route('/visite-medicale/creer', name: 'admin_appointment_medical_create', methods: ['GET', 'POST'])]
    public function createMedicalAppointment(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $appointment = new RendezVous();
        $form = $this->createForm(AppointmentMedicalType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Get form data
                $visitType = $form->get('visitType')->getData();
                $participant = $form->get('participants')->getData();

                // Generate subject based on visit type
                $typeLabels = [
                    'embauche' => 'Visite médicale - Embauche',
                    'periodique' => 'Visite médicale - Périodique',
                    'reprise' => 'Visite médicale - Reprise',
                    'demande' => 'Visite médicale - À la demande',
                ];
                $subject = $typeLabels[$visitType] ?? 'Visite médicale';

                // Set appointment type before creation
                $appointment->setType(RendezVous::TYPE_VISITE_MEDICALE);

                // Create the appointment
                $createdAppointment = $this->appointmentService->createConvocation(
                    organizer: $user,
                    subject: $subject,
                    scheduledAt: $appointment->getStartAt(),
                    durationMinutes: $appointment->getDurationMinutes(),
                    participants: [$participant],
                    description: $appointment->getDescription(),
                    location: $appointment->getLocation()
                );

                // Update type to VISITE_MEDICALE
                $createdAppointment->setType(RendezVous::TYPE_VISITE_MEDICALE);
                $this->appointmentService->updateAppointment($createdAppointment, []);

                // Create linked VisiteMedicale
                $visite = $this->medicalVisitService->createFromAppointment($createdAppointment, $visitType);

                $this->addFlash('success', 'Le rendez-vous et la visite médicale ont été créés avec succès.');

                return $this->redirectToRoute('admin_visite_medicale_show', ['id' => $visite->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }

        return $this->render('admin/appointment/create_medical.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Modifier un rendez-vous
     */
    #[Route('/{id}/modifier', name: 'admin_appointment_edit', methods: ['GET', 'POST'])]
    public function edit(RendezVous $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_EDIT', $appointment);

        $form = $this->createForm(AppointmentConvocationType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = [
                    'subject' => $appointment->getSubject(),
                    'scheduledAt' => $appointment->getStartAt(),
                    'durationMinutes' => $appointment->getDurationMinutes(),
                    'location' => $appointment->getLocation(),
                    'description' => $appointment->getDescription(),
                ];

                $this->appointmentService->updateAppointment($appointment, $data);

                $this->addFlash('success', 'Le rendez-vous a été modifié avec succès.');

                return $this->redirectToRoute('admin_appointment_show', ['id' => $appointment->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
            }
        }

        return $this->render('admin/appointment/edit.html.twig', [
            'appointment' => $appointment,
            'form' => $form,
        ]);
    }

    /**
     * Valider une demande de rendez-vous
     */
    #[Route('/{id}/valider', name: 'admin_appointment_validate', methods: ['GET', 'POST'])]
    public function validate(RendezVous $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_VALIDATE', $appointment);

        $form = $this->createForm(AppointmentValidationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var User $user */
                $user = $this->getUser();

                $data = $form->getData();

                $this->appointmentService->validateRequest(
                    appointment: $appointment,
                    validator: $user,
                    scheduledAt: $data['scheduledAt'],
                    durationMinutes: $data['durationMinutes'],
                    location: $data['location'] ?? null
                );

                $this->addFlash('success', 'La demande a été validée avec succès.');

                return $this->redirectToRoute('admin_appointment_pending');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la validation : ' . $e->getMessage());
            }
        }

        return $this->render('admin/appointment/validate.html.twig', [
            'appointment' => $appointment,
            'form' => $form,
        ]);
    }

    /**
     * Refuser une demande de rendez-vous
     */
    #[Route('/{id}/refuser', name: 'admin_appointment_refuse', methods: ['POST'])]
    public function refuse(RendezVous $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_VALIDATE', $appointment);

        $form = $this->createForm(AppointmentRefusalType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var User $user */
                $user = $this->getUser();

                $refusalReason = $form->get('refusalReason')->getData();

                $this->appointmentService->refuseRequest(
                    appointment: $appointment,
                    validator: $user,
                    refusalReason: $refusalReason
                );

                $this->addFlash('success', 'La demande a été refusée.');

                return $this->redirectToRoute('admin_appointment_pending');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors du refus : ' . $e->getMessage());
            }
        }

        // Si erreur de validation, retourner au dashboard
        $this->addFlash('error', 'Le motif de refus est obligatoire.');
        return $this->redirectToRoute('admin_appointment_pending');
    }

    /**
     * Détails d'un rendez-vous
     */
    #[Route('/{id}', name: 'admin_appointment_show', methods: ['GET'])]
    public function show(RendezVous $appointment): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_VIEW', $appointment);

        return $this->render('admin/appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    /**
     * Annuler un rendez-vous
     */
    #[Route('/{id}/annuler', name: 'admin_appointment_cancel', methods: ['POST'])]
    public function cancel(RendezVous $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('APPOINTMENT_DELETE', $appointment);

        if (!$this->isCsrfTokenValid('cancel-appointment-' . $appointment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_appointment_show', ['id' => $appointment->getId()]);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();

            $reason = $request->request->get('reason');

            $this->appointmentService->cancelAppointment(
                appointment: $appointment,
                canceledBy: $user,
                reason: $reason
            );

            $this->addFlash('success', 'Le rendez-vous a été annulé.');

            return $this->redirectToRoute('admin_appointment_index');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
            return $this->redirectToRoute('admin_appointment_show', ['id' => $appointment->getId()]);
        }
    }

    /**
     * Export CSV des rendez-vous
     */
    #[Route('/export', name: 'admin_appointment_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $filters = [];
        // Apply same filters as index

        $appointments = $this->appointmentRepository->findAllWithFilters($filters);

        $csv = "ID;Type;Statut;Objet;Organisateur;Date;Durée;Lieu;Participants\n";

        foreach ($appointments as $appointment) {
            $participants = [];
            foreach ($appointment->getAppointmentParticipants() as $participant) {
                $participants[] = $participant->getUser()->getFullName();
            }

            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s;%d;%s;%s\n",
                $appointment->getId(),
                $appointment->getType(),
                $appointment->getStatut(),
                $appointment->getSubject(),
                $appointment->getOrganizer()->getFullName(),
                $appointment->getStartAt()->format('Y-m-d H:i'),
                $appointment->getDurationMinutes(),
                $appointment->getLocation() ?? '',
                implode(', ', $participants)
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="rendez-vous-' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
