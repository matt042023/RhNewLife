<?php

namespace App\Service\Astreinte;

use App\Entity\Astreinte;
use App\Entity\User;
use App\Entity\Absence;
use App\Repository\AbsenceRepository;
use App\Repository\AstreinteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AstreinteNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AbsenceRepository $absenceRepository,
        private AstreinteRepository $astreinteRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        // TODO: Add MailerInterface for email notifications
    ) {}

    /**
     * Check if educator is absent during astreinte period
     */
    public function checkEducateurAbsence(Astreinte $astreinte): bool
    {
        if (!$astreinte->getEducateur()) {
            return false;
        }

        $absences = $this->absenceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.status = :approvedStatus')
            ->andWhere('a.startAt <= :periodEnd')
            ->andWhere('a.endAt >= :periodStart')
            ->setParameter('user', $astreinte->getEducateur())
            ->setParameter('approvedStatus', Absence::STATUS_APPROVED)
            ->setParameter('periodStart', $astreinte->getStartAt())
            ->setParameter('periodEnd', $astreinte->getEndAt())
            ->getQuery()
            ->getResult();

        return !empty($absences);
    }

    /**
     * Update astreinte status if educator becomes absent
     */
    public function updateAstreinteStatus(Astreinte $astreinte): void
    {
        if ($this->checkEducateurAbsence($astreinte)) {
            $astreinte->setStatus(Astreinte::STATUS_ALERT);
            $this->astreinteRepository->save($astreinte, true);

            $this->logger->warning('Astreinte educator is absent', [
                'astreinte_id' => $astreinte->getId(),
                'educateur' => $astreinte->getEducateur()->getFullName(),
                'period' => $astreinte->getPeriodLabel(),
            ]);

            // TODO: Send email notification to admins
        } elseif ($astreinte->getEducateur()) {
            $astreinte->setStatus(Astreinte::STATUS_ASSIGNED);
            $this->astreinteRepository->save($astreinte, true);
        }
    }

    /**
     * Check all current and upcoming astreintes for absent educators
     *
     * @return Astreinte[]
     */
    public function checkAllAstreintes(): array
    {
        $now = new \DateTime();
        $futureLimit = (new \DateTime())->modify('+30 days');

        $astreintes = $this->astreinteRepository->createQueryBuilder('a')
            ->where('a.endAt >= :now')
            ->andWhere('a.startAt <= :futureLimit')
            ->andWhere('a.educateur IS NOT NULL')
            ->setParameter('now', $now)
            ->setParameter('futureLimit', $futureLimit)
            ->getQuery()
            ->getResult();

        $alerts = [];
        foreach ($astreintes as $astreinte) {
            $this->updateAstreinteStatus($astreinte);
            if ($astreinte->getStatus() === Astreinte::STATUS_ALERT) {
                $alerts[] = $astreinte;
            }
        }

        return $alerts;
    }

    /**
     * Get available educators for a specific period (not absent, not already on-call)
     *
     * @return User[]
     */
    public function getAvailableEducateurs(\DateTimeInterface $startAt, \DateTimeInterface $endAt): array
    {
        // Get all active educators
        $allEducateurs = $this->userRepository->createQueryBuilder('u')
            ->where('u.status = :activeStatus')
            ->setParameter('activeStatus', User::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();

        $available = [];
        foreach ($allEducateurs as $educateur) {
            // Check if educator is absent during this period
            $absences = $this->absenceRepository->createQueryBuilder('a')
                ->where('a.user = :user')
                ->andWhere('a.status = :approvedStatus')
                ->andWhere('a.startAt <= :periodEnd')
                ->andWhere('a.endAt >= :periodStart')
                ->setParameter('user', $educateur)
                ->setParameter('approvedStatus', Absence::STATUS_APPROVED)
                ->setParameter('periodStart', $startAt)
                ->setParameter('periodEnd', $endAt)
                ->getQuery()
                ->getResult();

            if (empty($absences)) {
                $available[] = $educateur;
            }
        }

        return $available;
    }
}
