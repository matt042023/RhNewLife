<?php

namespace App\Service\MedicalVisit;

use App\Entity\RendezVous;
use App\Entity\VisiteMedicale;
use App\Repository\VisiteMedicaleRepository;
use App\Service\Appointment\AppointmentNotificationService;
use App\Service\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MedicalVisitService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VisiteMedicaleRepository $visiteMedicaleRepository,
        private AppointmentNotificationService $notificationService,
        private DocumentManager $documentManager
    ) {
    }

    /**
     * Create a VisiteMedicale from an appointment
     * Called when creating a medical appointment
     */
    public function createFromAppointment(RendezVous $appointment, string $visitType): VisiteMedicale
    {
        // Get the first participant (the employee)
        $participants = $appointment->getAppointmentParticipants();
        if ($participants->isEmpty()) {
            throw new \RuntimeException('L\'appointment doit avoir au moins un participant');
        }

        $user = $participants->first()->getUser();

        // Create the medical visit
        $visite = new VisiteMedicale();
        $visite->setUser($user);
        $visite->setType($visitType);
        $visite->setStatus(VisiteMedicale::STATUS_PROGRAMMEE);
        $visite->setAppointment($appointment);

        // Save
        $this->entityManager->persist($visite);
        $this->entityManager->flush();

        return $visite;
    }

    /**
     * Complete medical visit with results
     * Called by admin after the visit is done
     */
    public function completeMedicalVisit(VisiteMedicale $visite, array $data, ?UploadedFile $certificateFile = null): void
    {
        // Validation
        if (!$visite->isProgrammee()) {
            throw new \RuntimeException('Seules les visites programmées peuvent être complétées');
        }

        // Update visit data
        $visite->setStatus(VisiteMedicale::STATUS_EFFECTUEE);
        $visite->setVisitDate($data['visitDate']);
        $visite->setExpiryDate($data['expiryDate']);
        $visite->setAptitude($data['aptitude']);
        $visite->setMedicalOrganization($data['medicalOrganization']);

        if (isset($data['observations'])) {
            $visite->setObservations($data['observations']);
        }

        $this->entityManager->flush();

        // Upload certificate if provided
        if ($certificateFile) {
            $document = $this->documentManager->uploadDocument(
                file: $certificateFile,
                user: $visite->getUser(),
                type: 'medical_certificate',
                uploadedBy: $data['uploadedBy'] ?? null
            );

            $document->setVisiteMedicale($visite);
            $this->entityManager->flush();
        }

        // Send notification to employee
        $this->notifyEmployeeOfResults($visite);
    }

    /**
     * Cancel a medical visit
     */
    public function cancelMedicalVisit(VisiteMedicale $visite, string $reason): void
    {
        $visite->setStatus(VisiteMedicale::STATUS_ANNULEE);
        $visite->setObservations($visite->getObservations() . "\n\nAnnulation: " . $reason);

        $this->entityManager->flush();
    }

    /**
     * Update an existing medical visit (only if effectuee)
     */
    public function updateMedicalVisit(VisiteMedicale $visite, array $data, ?UploadedFile $certificateFile = null): void
    {
        if (!$visite->isEffectuee()) {
            throw new \RuntimeException('Seules les visites effectuées peuvent être modifiées');
        }

        // Update data
        if (isset($data['visitDate'])) {
            $visite->setVisitDate($data['visitDate']);
        }
        if (isset($data['expiryDate'])) {
            $visite->setExpiryDate($data['expiryDate']);
        }
        if (isset($data['aptitude'])) {
            $visite->setAptitude($data['aptitude']);
        }
        if (isset($data['medicalOrganization'])) {
            $visite->setMedicalOrganization($data['medicalOrganization']);
        }
        if (isset($data['observations'])) {
            $visite->setObservations($data['observations']);
        }

        $this->entityManager->flush();

        // Upload new certificate if provided
        if ($certificateFile) {
            $document = $this->documentManager->uploadDocument(
                file: $certificateFile,
                user: $visite->getUser(),
                type: 'medical_certificate',
                uploadedBy: $data['uploadedBy'] ?? null
            );

            $document->setVisiteMedicale($visite);
            $this->entityManager->flush();
        }
    }

    /**
     * Delete a medical visit
     */
    public function deleteMedicalVisit(VisiteMedicale $visite): void
    {
        // Note: documents are not deleted, only unlinked (SET NULL in DB)
        $this->entityManager->remove($visite);
        $this->entityManager->flush();
    }

    /**
     * Send notification to employee about medical visit results
     */
    private function notifyEmployeeOfResults(VisiteMedicale $visite): void
    {
        // Use the appointment notification service to send notification
        // This assumes the AppointmentNotificationService can handle custom messages

        // For now, we'll skip this if no appointment is linked
        if (!$visite->getAppointment()) {
            return;
        }

        // The notification will be sent via the appointment system
        // Customize this based on your notification implementation

        // Example: Send email or in-app notification
        // $this->notificationService->notifyMedicalVisitCompleted($visite);
    }

    /**
     * Get upcoming medical visit renewals
     */
    public function getUpcomingRenewals(int $days = 30): array
    {
        return $this->visiteMedicaleRepository->findUpcomingRenewals($days);
    }

    /**
     * Get expired medical visits
     */
    public function getExpiredVisits(): array
    {
        return $this->visiteMedicaleRepository->findExpired();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        return $this->visiteMedicaleRepository->getStatistics();
    }
}
