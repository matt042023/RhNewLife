<?php

namespace App\Service\Planning;

use App\Entity\Affectation;
use App\Entity\PlanningMonth;
use App\Entity\User;
use App\Entity\Villa;
use Doctrine\ORM\EntityManagerInterface;

class VillaPlanningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanningConflictService $conflictService
    ) {
    }

    public function assignUser(Affectation $affectation, ?User $user): void
    {
        $affectation->setUser($user);
        
        if ($user) {
            $this->conflictService->checkAndResolveConflicts($affectation);
        } else {
            // If unassigned, reset status to draft (unless it was something else)
            $affectation->setStatut(Affectation::STATUS_DRAFT);
        }

        $this->em->flush();
    }

    public function validatePlanning(PlanningMonth $planning, User $validator): void
    {
        // 1. Check if all affectations are valid (optional: prevent validation if conflicts exist)
        // For now, we allow validation even with conflicts, but maybe we should warn.
        
        $planning->setStatut(PlanningMonth::STATUS_VALIDATED);
        $planning->setDateValidation(new \DateTime());
        $planning->setValidePar($validator);

        // Update all affectations to validated if they were draft
        foreach ($planning->getAffectations() as $affectation) {
            if ($affectation->getStatut() === Affectation::STATUS_DRAFT) {
                $affectation->setStatut(Affectation::STATUS_VALIDATED);
            }
        }

        $this->em->flush();
    }

    public function createManualAffectation(PlanningMonth $planning, Villa $villa, \DateTimeInterface $start, \DateTimeInterface $end, string $type, ?User $user = null): Affectation
    {
        $affectation = new Affectation();
        $affectation->setPlanningMois($planning);
        $affectation->setVilla($villa);
        $affectation->setStartAt($start);
        $affectation->setEndAt($end);
        $affectation->setType($type);
        $affectation->setIsFromSquelette(false);
        $affectation->setStatut(Affectation::STATUS_DRAFT);
        
        if ($user) {
            $this->assignUser($affectation, $user);
        } else {
            $this->em->persist($affectation);
            $this->em->flush();
        }

        return $affectation;
    }
}
