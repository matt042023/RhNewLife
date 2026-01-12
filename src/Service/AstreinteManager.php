<?php

namespace App\Service;

use App\Entity\Astreinte;
use App\Entity\User;
use App\Repository\AstreinteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AstreinteManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AstreinteRepository $astreinteRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a new astreinte period
     */
    public function createAstreinte(
        \DateTimeInterface $startAt,
        \DateTimeInterface $endAt,
        ?User $educateur = null,
        ?string $periodLabel = null,
        ?User $createdBy = null,
        ?string $notes = null
    ): Astreinte {
        // Validation
        $this->validateDates($startAt, $endAt);
        $this->checkNoOverlap($startAt, $endAt);

        $astreinte = new Astreinte();
        $astreinte
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setEducateur($educateur)
            ->setPeriodLabel($periodLabel)
            ->setNotes($notes)
            ->setCreatedBy($createdBy);

        // Auto-calculate status
        $this->updateStatus($astreinte);

        $this->em->persist($astreinte);
        $this->em->flush();

        $this->logger->info('Astreinte created', [
            'id' => $astreinte->getId(),
            'period' => $periodLabel,
            'educateur' => $educateur?->getFullName(),
        ]);

        return $astreinte;
    }

    /**
     * Assign an educator to an astreinte
     */
    public function assignEducateur(Astreinte $astreinte, ?User $educateur, ?User $updatedBy = null): void
    {
        $astreinte->setEducateur($educateur);
        $astreinte->setUpdatedBy($updatedBy);

        $this->updateStatus($astreinte);

        $this->em->flush();

        $this->logger->info('Astreinte assigned', [
            'id' => $astreinte->getId(),
            'educateur' => $educateur?->getFullName(),
        ]);
    }

    /**
     * Increment replacement count
     */
    public function recordReplacement(Astreinte $astreinte, ?string $notes = null): void
    {
        $astreinte->incrementReplacementCount();

        if ($notes) {
            $currentNotes = $astreinte->getNotes() ?? '';
            $timestamp = (new \DateTime())->format('Y-m-d H:i');
            $astreinte->setNotes($currentNotes . "\n[$timestamp] Remplacement: $notes");
        }

        $this->em->flush();
    }

    /**
     * Update astreinte
     */
    public function updateAstreinte(
        Astreinte $astreinte,
        \DateTimeInterface $startAt,
        \DateTimeInterface $endAt,
        ?User $educateur,
        ?string $periodLabel,
        ?User $updatedBy,
        ?string $notes
    ): void {
        // Validation
        $this->validateDates($startAt, $endAt);
        $this->checkNoOverlap($startAt, $endAt, $astreinte->getId());

        $astreinte
            ->setStartAt($startAt)
            ->setEndAt($endAt)
            ->setEducateur($educateur)
            ->setPeriodLabel($periodLabel)
            ->setNotes($notes)
            ->setUpdatedBy($updatedBy);

        $this->updateStatus($astreinte);

        $this->em->flush();
    }

    /**
     * Delete astreinte
     */
    public function deleteAstreinte(Astreinte $astreinte): void
    {
        $this->em->remove($astreinte);
        $this->em->flush();

        $this->logger->info('Astreinte deleted', ['id' => $astreinte->getId()]);
    }

    /**
     * Auto-update status based on educateur assignment
     */
    private function updateStatus(Astreinte $astreinte): void
    {
        if ($astreinte->getEducateur() === null) {
            $astreinte->setStatus(Astreinte::STATUS_UNASSIGNED);
        } else {
            // Check if educateur is absent during this period will be done by AstreinteNotificationService
            $astreinte->setStatus(Astreinte::STATUS_ASSIGNED);
        }
    }

    /**
     * Validate dates
     */
    private function validateDates(\DateTimeInterface $startAt, \DateTimeInterface $endAt): void
    {
        if ($startAt >= $endAt) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début.');
        }
    }

    /**
     * Check for overlapping astreintes
     */
    private function checkNoOverlap(\DateTimeInterface $startAt, \DateTimeInterface $endAt, ?int $excludeId = null): void
    {
        $overlapping = $this->astreinteRepository->findOverlapping($startAt, $endAt, $excludeId);

        if (!empty($overlapping)) {
            throw new \InvalidArgumentException('Cette période chevauche une astreinte existante.');
        }
    }

    /**
     * Generate weekly astreintes for a month
     *
     * Important: Weeks are Monday-to-Sunday and can span across month boundaries.
     * - A week that STARTS before the month will be created (e.g., S01 2026 starts Dec 29)
     * - A week that ENDS after the month will extend into next month (e.g., S05 ends Feb 1)
     */
    public function generateMonthlyAstreintes(int $year, int $month, ?User $createdBy = null): array
    {
        $startOfMonth = new \DateTime("$year-$month-01");
        $endOfMonth = (clone $startOfMonth)->modify('last day of this month');

        $astreintes = [];

        // IMPORTANT: Start from Monday of the week CONTAINING the 1st day of the month
        // This may be in the PREVIOUS month (e.g., S01 2026 starts Dec 29, 2025)
        $currentStart = clone $startOfMonth;

        // Find the Monday of the week containing day 1
        while ($currentStart->format('N') != 1) {  // N=1 is Monday
            $currentStart->modify('-1 day');  // Go BACK to find the Monday
        }

        // Generate weekly periods (Monday to Sunday)
        // Stop when we've processed all weeks that touch this month
        while ($currentStart <= $endOfMonth) {
            // Calculate Sunday of this week (6 days after Monday)
            $currentEnd = (clone $currentStart)->modify('+6 days')->setTime(23, 59, 59);

            $weekNumber = (int) $currentStart->format('W');
            $periodLabel = "S$weekNumber";

            try {
                $astreinte = $this->createAstreinte(
                    startAt: $currentStart,
                    endAt: $currentEnd,
                    periodLabel: $periodLabel,
                    createdBy: $createdBy
                );
                $astreintes[] = $astreinte;
            } catch (\Exception $e) {
                $this->logger->warning('Failed to create astreinte', [
                    'period' => $periodLabel,
                    'start' => $currentStart->format('Y-m-d'),
                    'end' => $currentEnd->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);
            }

            // Move to next Monday (+7 days from current Monday)
            $currentStart = (clone $currentStart)->modify('+7 days');
        }

        return $astreintes;
    }
}
