<?php

namespace App\EventListener;

use App\Domain\Event\ContractSignedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Listener pour l'event ContractSigned
 * Actions:
 * - Notification à l'employé que son contrat signé est disponible
 * - Log de l'événement
 */
#[AsEventListener(event: ContractSignedEvent::NAME)]
class ContractSignedListener
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'noreply@rhnewlife.fr',
        private string $senderName = 'RH NewLife'
    ) {
    }

    public function __invoke(ContractSignedEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();

        $this->logger->info('Contract signed', [
            'contract_id' => $contract->getId(),
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
        ]);

        // Envoyer email à l'utilisateur
        try {
            $this->sendContractSignedNotification($contract);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send contract signed notification', [
                'contract_id' => $contract->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendContractSignedNotification($contract): void
    {
        $user = $contract->getUser();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('Votre contrat signé est disponible')
            ->htmlTemplate('emails/contract_signed_available.html.twig')
            ->context([
                'user' => $user,
                'contract' => $contract,
            ]);

        $this->mailer->send($email);
    }
}
