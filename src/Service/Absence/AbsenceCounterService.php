<?php

namespace App\Service\Absence;

use App\Entity\Absence;
use App\Entity\CompteurAbsence;
use App\Entity\TypeAbsence;
use App\Entity\User;
use App\Repository\CompteurAbsenceRepository;
use App\Service\Payroll\CPCounterService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AbsenceCounterService
{
    // Code du type d'absence "Congés Payés" à synchroniser avec le module paie
    private const CP_ABSENCE_TYPE_CODE = 'CP';

    public function __construct(
        private EntityManagerInterface $em,
        private CompteurAbsenceRepository $compteurRepository,
        private CPCounterService $cpCounterService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Calculate working days between two dates (excluding weekends and public holidays)
     */
    public function calculateWorkingDays(
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): float {
        $workingDays = 0;
        $current = clone $start;
        $endDate = clone $end;

        while ($current <= $endDate) {
            $dayOfWeek = (int) $current->format('N'); // 1=Monday, 7=Sunday

            // Exclude weekends
            if ($dayOfWeek >= 6) {
                $current->modify('+1 day');
                continue;
            }

            // Exclude public holidays
            if ($this->isPublicHoliday($current)) {
                $current->modify('+1 day');
                continue;
            }

            $workingDays++;
            $current->modify('+1 day');
        }

        return $workingDays;
    }

    /**
     * Get or create counter for user, type and year
     */
    public function getOrCreateCounter(
        User $user,
        TypeAbsence $absenceType,
        int $year
    ): CompteurAbsence {
        $counter = $this->compteurRepository->findByUserTypeAndYear($user, $absenceType, $year);

        if (!$counter) {
            $counter = new CompteurAbsence();
            $counter
                ->setUser($user)
                ->setAbsenceType($absenceType)
                ->setYear($year)
                ->setEarned(0)
                ->setTaken(0);

            $this->em->persist($counter);
            $this->em->flush();

            $this->logger->info('Counter created', [
                'user_id' => $user->getId(),
                'absence_type' => $absenceType->getCode(),
                'year' => $year,
            ]);
        }

        return $counter;
    }

    /**
     * Deduct days from counter after absence approval
     */
    public function deductDays(Absence $absence): void
    {
        if (!$absence->getAbsenceType()?->isDeductFromCounter()) {
            return;
        }

        $year = (int) $absence->getStartAt()->format('Y');
        $counter = $this->getOrCreateCounter(
            $absence->getUser(),
            $absence->getAbsenceType(),
            $year
        );

        $workingDays = $absence->getWorkingDaysCount() ?? 0;
        $counter->setTaken($counter->getTaken() + $workingDays);
        $this->em->flush();

        $this->logger->info('Counter deducted', [
            'absence_id' => $absence->getId(),
            'user_id' => $absence->getUser()->getId(),
            'days_deducted' => $workingDays,
            'remaining' => $counter->getRemaining(),
        ]);

        // Alert if negative balance
        if ($counter->isNegative()) {
            $this->logger->warning('Negative counter balance', [
                'user_id' => $absence->getUser()->getId(),
                'absence_type' => $absence->getAbsenceType()->getCode(),
                'balance' => $counter->getRemaining(),
            ]);
        }

        // Synchronize with payroll CP counter if this is a "Congés Payés" absence
        $this->syncWithPayrollCPCounter($absence, $workingDays, 'deduct');
    }

    /**
     * Credit days back to counter (e.g., when absence is cancelled)
     */
    public function creditDays(Absence $absence): void
    {
        if (!$absence->getAbsenceType()?->isDeductFromCounter()) {
            return;
        }

        if (!$absence->isApproved()) {
            return; // Only credit if absence was approved
        }

        $year = (int) $absence->getStartAt()->format('Y');
        $counter = $this->getOrCreateCounter(
            $absence->getUser(),
            $absence->getAbsenceType(),
            $year
        );

        $workingDays = $absence->getWorkingDaysCount() ?? 0;
        $counter->setTaken(max(0, $counter->getTaken() - $workingDays));
        $this->em->flush();

        $this->logger->info('Counter credited', [
            'absence_id' => $absence->getId(),
            'user_id' => $absence->getUser()->getId(),
            'days_credited' => $workingDays,
            'remaining' => $counter->getRemaining(),
        ]);

        // Synchronize with payroll CP counter if this is a "Congés Payés" absence
        $this->syncWithPayrollCPCounter($absence, $workingDays, 'credit');
    }

    /**
     * Check if user has sufficient balance for requested days
     */
    public function checkSufficientBalance(
        User $user,
        TypeAbsence $absenceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): void {
        if (!$absenceType->isDeductFromCounter()) {
            return; // No check needed if type doesn't deduct from counter
        }

        $year = (int) $start->format('Y');
        $counter = $this->getOrCreateCounter($user, $absenceType, $year);

        $requiredDays = $this->calculateWorkingDays($start, $end);

        if (!$counter->hasSufficientBalance($requiredDays)) {
            throw new \LogicException(sprintf(
                'Solde insuffisant pour %s : %s jours disponibles, %s jours demandés',
                $absenceType->getLabel(),
                $counter->getRemaining(),
                $requiredDays
            ));
        }
    }

    /**
     * Get all counters for a user in a specific year
     *
     * @return CompteurAbsence[]
     */
    public function getUserCounters(User $user, int $year): array
    {
        return $this->compteurRepository->findByUserAndYear($user, $year);
    }

    /**
     * Initialize counters for a new year (e.g., CP earned)
     */
    public function initializeYearlyCounters(User $user, int $year, array $earnings = []): void
    {
        foreach ($earnings as $typeCode => $earnedDays) {
            $absenceType = $this->em->getRepository(TypeAbsence::class)->findByCode($typeCode);

            if (!$absenceType || !$absenceType->isDeductFromCounter()) {
                continue;
            }

            $counter = $this->getOrCreateCounter($user, $absenceType, $year);
            $counter->setEarned($earnedDays);
            $this->em->flush();

            $this->logger->info('Yearly counter initialized', [
                'user_id' => $user->getId(),
                'absence_type' => $typeCode,
                'year' => $year,
                'earned' => $earnedDays,
            ]);
        }
    }

    /**
     * Check if a date is a public holiday (France)
     * TODO: Move to a dedicated PublicHolidayService or load from database
     */
    private function isPublicHoliday(\DateTimeInterface $date): bool
    {
        $year = (int) $date->format('Y');
        $holidays = $this->getFrenchPublicHolidays($year);

        $dateString = $date->format('Y-m-d');

        return in_array($dateString, $holidays, true);
    }

    /**
     * Get French public holidays for a given year
     * Source: https://www.service-public.fr/particuliers/vosdroits/F2405
     *
     * @return string[] Array of dates in Y-m-d format
     */
    private function getFrenchPublicHolidays(int $year): array
    {
        // Fixed holidays
        $holidays = [
            "$year-01-01", // Jour de l'an
            "$year-05-01", // Fête du travail
            "$year-05-08", // Victoire 1945
            "$year-07-14", // Fête nationale
            "$year-08-15", // Assomption
            "$year-11-01", // Toussaint
            "$year-11-11", // Armistice 1918
            "$year-12-25", // Noël
        ];

        // Easter-based holidays (movable)
        $easterDate = new \DateTime("$year-03-21");
        $easterDate->modify('+' . easter_days($year) . ' days');

        $easterMonday = clone $easterDate;
        $easterMonday->modify('+1 day');
        $holidays[] = $easterMonday->format('Y-m-d'); // Lundi de Pâques

        $ascension = clone $easterDate;
        $ascension->modify('+39 days');
        $holidays[] = $ascension->format('Y-m-d'); // Ascension

        $pentecostMonday = clone $easterDate;
        $pentecostMonday->modify('+50 days');
        $holidays[] = $pentecostMonday->format('Y-m-d'); // Lundi de Pentecôte

        return $holidays;
    }

    /**
     * Synchronize absence with payroll CP counter when the absence type is "Congés Payés"
     * This ensures both the absence module counter and the payroll module counter stay in sync
     *
     * @param Absence $absence The absence being processed
     * @param float $workingDays Number of working days
     * @param string $operation Either 'deduct' or 'credit'
     */
    private function syncWithPayrollCPCounter(Absence $absence, float $workingDays, string $operation): void
    {
        $absenceTypeCode = $absence->getAbsenceType()?->getCode();

        // Only sync if this is a "Congés Payés" (CP) absence type
        if ($absenceTypeCode !== self::CP_ABSENCE_TYPE_CODE) {
            return;
        }

        if ($workingDays <= 0) {
            return;
        }

        $user = $absence->getUser();

        if ($operation === 'deduct') {
            $this->cpCounterService->deductCP($user, $workingDays);
            $this->logger->info('CP absence synced with payroll counter (deducted)', [
                'absence_id' => $absence->getId(),
                'user_id' => $user->getId(),
                'days' => $workingDays,
            ]);
        } elseif ($operation === 'credit') {
            $this->cpCounterService->cancelDeduction($user, $workingDays);
            $this->logger->info('CP absence synced with payroll counter (credited back)', [
                'absence_id' => $absence->getId(),
                'user_id' => $user->getId(),
                'days' => $workingDays,
            ]);
        }
    }
}
