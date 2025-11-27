<?php

namespace App\Service\Absence;

use App\Entity\Absence;
use App\Entity\Document;
use App\Entity\TypeAbsence;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Service\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AbsenceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AbsenceRepository $absenceRepository,
        private DocumentManager $documentManager,
        private AbsenceCounterService $counterService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new absence request
     */
    public function createAbsence(
        User $user,
        TypeAbsence $absenceType,
        \DateTimeInterface $startAt,
        \DateTimeInterface $endAt,
        ?string $reason = null,
        ?UploadedFile $justificationFile = null
    ): Absence {
        // Validations
        $this->validateDates($startAt, $endAt);
        $this->checkOverlap($user, $startAt, $endAt);

        // Check sufficient balance if needed
        if ($absenceType->isDeductFromCounter()) {
            $this->counterService->checkSufficientBalance($user, $absenceType, $startAt, $endAt);
        }

        // Create absence
        $absence = new Absence();
        $absence
            ->setUser($user)
            ->setAbsenceType($absenceType)
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setReason($reason)
            ->setStatus(Absence::STATUS_PENDING)
            ->setAffectsPlanning($absenceType->isAffectsPlanning());

        // Calculate working days
        $workingDays = $this->counterService->calculateWorkingDays($startAt, $endAt);
        $absence->setWorkingDaysCount($workingDays);

        // Initialize justification status
        if ($absenceType->isRequiresJustification()) {
            $deadline = $this->calculateJustificationDeadline($startAt, $absenceType);
            $absence
                ->setJustificationStatus(Absence::JUSTIF_PENDING)
                ->setJustificationDeadline($deadline);

            // Immediate upload if file provided
            if ($justificationFile) {
                $this->em->persist($absence);
                $this->em->flush();

                $this->addJustification($absence, $justificationFile, $user);
                $absence->setJustificationStatus(Absence::JUSTIF_PROVIDED);
            }
        } else {
            $absence->setJustificationStatus(Absence::JUSTIF_NOT_REQUIRED);
        }

        $this->em->persist($absence);
        $this->em->flush();

        $this->logger->info('Absence created', [
            'absence_id' => $absence->getId(),
            'user_id' => $user->getId(),
            'type' => $absenceType->getCode(),
            'start' => $startAt->format('Y-m-d'),
            'end' => $endAt->format('Y-m-d'),
            'working_days' => $workingDays,
            'requires_justification' => $absenceType->isRequiresJustification(),
        ]);

        return $absence;
    }

    /**
     * Validate an absence request (admin action)
     */
    public function validateAbsence(
        Absence $absence,
        User $validator,
        bool $forceWithoutJustification = false
    ): void {
        // Check justification requirement
        if ($absence->requiresJustification() && !$absence->hasValidJustification()) {
            if (!$forceWithoutJustification) {
                throw new \LogicException(
                    'Impossible de valider : justificatif obligatoire non validé. '
                    . 'Utilisez forceWithoutJustification=true pour forcer.'
                );
            }

            $this->logger->warning('Absence validated without required justification', [
                'absence_id' => $absence->getId(),
                'validator_id' => $validator->getId(),
                'forced' => true,
            ]);
        }

        $absence
            ->setStatus(Absence::STATUS_APPROVED)
            ->setValidatedBy($validator);

        $this->em->flush();

        // Deduct from counter if needed
        if ($absence->getAbsenceType()->isDeductFromCounter()) {
            $this->counterService->deductDays($absence);
        }

        $this->logger->info('Absence validated', [
            'absence_id' => $absence->getId(),
            'validator_id' => $validator->getId(),
            'user_id' => $absence->getUser()->getId(),
        ]);
    }

    /**
     * Reject an absence request
     */
    public function rejectAbsence(Absence $absence, User $validator, string $reason): void
    {
        if (empty($reason)) {
            throw new \InvalidArgumentException('Un motif de refus est obligatoire');
        }

        $absence
            ->setStatus(Absence::STATUS_REJECTED)
            ->setValidatedBy($validator)
            ->setRejectionReason($reason);

        $this->em->flush();

        $this->logger->info('Absence rejected', [
            'absence_id' => $absence->getId(),
            'validator_id' => $validator->getId(),
            'user_id' => $absence->getUser()->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Cancel an absence
     */
    public function cancelAbsence(Absence $absence, User $user): void
    {
        if (!$absence->canBeCancelled()) {
            throw new \LogicException('Cette absence ne peut plus être annulée');
        }

        $wasApproved = $absence->isApproved();

        $absence->setStatus(Absence::STATUS_CANCELLED);
        $this->em->flush();

        // Credit back days if was approved
        if ($wasApproved) {
            $this->counterService->creditDays($absence);
        }

        $this->logger->info('Absence cancelled', [
            'absence_id' => $absence->getId(),
            'user_id' => $user->getId(),
            'was_approved' => $wasApproved,
        ]);
    }

    /**
     * Add a justification document to an absence
     */
    public function addJustification(
        Absence $absence,
        UploadedFile $file,
        User $uploadedBy
    ): Document {
        $documentType = $absence->getAbsenceType()->getDocumentType()
            ?? Document::TYPE_ABSENCE_JUSTIFICATION;

        // Reuse DocumentManager service
        $document = $this->documentManager->uploadDocument(
            file: $file,
            user: $absence->getUser(),
            type: $documentType,
            comment: "Justificatif pour absence du " . $absence->getStartAt()->format('d/m/Y'),
            uploadedBy: $uploadedBy
        );

        // Link with absence
        $document->setAbsence($absence);
        $this->em->flush();

        // Update absence justification status
        $absence->setJustificationStatus(Absence::JUSTIF_PROVIDED);
        $this->em->flush();

        $this->logger->info('Justification uploaded', [
            'absence_id' => $absence->getId(),
            'document_id' => $document->getId(),
            'uploaded_by_id' => $uploadedBy->getId(),
        ]);

        return $document;
    }

    /**
     * Validate a justification document
     */
    public function validateJustification(Document $document, User $validator, ?string $comment = null): void
    {
        // Reuse DocumentManager
        $this->documentManager->validateDocument($document, $comment, $validator);

        // Update absence justification status
        $absence = $document->getAbsence();
        if ($absence) {
            $absence->setJustificationStatus(Absence::JUSTIF_VALIDATED);
            $this->em->flush();

            $this->logger->info('Justification validated', [
                'absence_id' => $absence->getId(),
                'document_id' => $document->getId(),
                'validator_id' => $validator->getId(),
            ]);
        }
    }

    /**
     * Reject a justification document with new deadline
     */
    public function rejectJustification(
        Document $document,
        User $validator,
        string $reason
    ): void {
        // Reuse DocumentManager
        $this->documentManager->rejectDocument($document, $reason);

        $absence = $document->getAbsence();
        if ($absence) {
            // Reset status + new deadline (+2 days)
            $newDeadline = (new \DateTime())->modify('+2 days');
            $absence
                ->setJustificationStatus(Absence::JUSTIF_PENDING)
                ->setJustificationDeadline($newDeadline);

            $this->em->flush();

            $this->logger->info('Justification rejected', [
                'absence_id' => $absence->getId(),
                'document_id' => $document->getId(),
                'validator_id' => $validator->getId(),
                'new_deadline' => $newDeadline->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Calculate justification deadline based on absence start date and type
     */
    private function calculateJustificationDeadline(
        \DateTimeInterface $absenceStart,
        TypeAbsence $absenceType
    ): \DateTimeInterface {
        $deadline = clone $absenceStart;
        $days = $absenceType->getJustificationDeadlineDays() ?? 2;
        $deadline->modify("+{$days} days");

        return $deadline;
    }

    /**
     * Check for overlapping absences
     */
    private function checkOverlap(User $user, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $qb = $this->absenceRepository->createQueryBuilder('a');
        $qb
            ->where('a.user = :user')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere(
                $qb->expr()->orX(
                    // New absence overlaps existing absence
                    $qb->expr()->andX(
                        $qb->expr()->lte('a.startAt', ':end'),
                        $qb->expr()->gte('a.endAt', ':start')
                    )
                )
            )
            ->setParameter('user', $user)
            ->setParameter('statuses', [Absence::STATUS_PENDING, Absence::STATUS_APPROVED])
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1);

        if ($qb->getQuery()->getOneOrNullResult()) {
            throw new \LogicException('Une absence existe déjà sur cette période');
        }
    }

    /**
     * Validate dates coherence
     */
    private function validateDates(\DateTimeInterface $start, \DateTimeInterface $end): void
    {
        if ($end < $start) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
        }
    }

    /**
     * Get absences for a user
     *
     * @return Absence[]
     */
    public function getUserAbsences(User $user, ?string $status = null): array
    {
        $qb = $this->absenceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.startAt', 'DESC');

        if ($status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get pending absences (for admin)
     *
     * @return Absence[]
     */
    public function getPendingAbsences(): array
    {
        return $this->absenceRepository->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', Absence::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get absences with overdue justifications
     *
     * @return Absence[]
     */
    public function getAbsencesWithOverdueJustifications(): array
    {
        return $this->absenceRepository->createQueryBuilder('a')
            ->where('a.justificationStatus = :status')
            ->andWhere('a.justificationDeadline < :now')
            ->setParameter('status', Absence::JUSTIF_PENDING)
            ->setParameter('now', new \DateTime())
            ->orderBy('a.justificationDeadline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get absences with justifications due tomorrow (for reminders)
     *
     * @return Absence[]
     */
    public function getAbsencesWithJustificationsDueTomorrow(): array
    {
        $tomorrow = (new \DateTime())->modify('+1 day');
        $tomorrowStart = (clone $tomorrow)->setTime(0, 0, 0);
        $tomorrowEnd = (clone $tomorrow)->setTime(23, 59, 59);

        return $this->absenceRepository->createQueryBuilder('a')
            ->where('a.justificationStatus = :status')
            ->andWhere('a.justificationDeadline BETWEEN :start AND :end')
            ->setParameter('status', Absence::JUSTIF_PENDING)
            ->setParameter('start', $tomorrowStart)
            ->setParameter('end', $tomorrowEnd)
            ->orderBy('a.justificationDeadline', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
