<?php

namespace App\Domain\Event;

use App\Entity\ProfileUpdateRequest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatché quand un utilisateur demande une modification de profil
 */
class ProfileUpdateRequestedEvent extends Event
{
    public const NAME = 'profile_update.requested';

    public function __construct(
        private ProfileUpdateRequest $request
    ) {
    }

    public function getRequest(): ProfileUpdateRequest
    {
        return $this->request;
    }
}
