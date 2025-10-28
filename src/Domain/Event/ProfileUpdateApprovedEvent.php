<?php

namespace App\Domain\Event;

use App\Entity\ProfileUpdateRequest;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatché quand une demande de modification est approuvée
 */
class ProfileUpdateApprovedEvent extends Event
{
    public const NAME = 'profile_update.approved';

    public function __construct(
        private ProfileUpdateRequest $request,
        private User $approvedBy
    ) {
    }

    public function getRequest(): ProfileUpdateRequest
    {
        return $this->request;
    }

    public function getApprovedBy(): User
    {
        return $this->approvedBy;
    }
}
