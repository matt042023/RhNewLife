<?php

namespace App\Service\Payroll;

use App\Entity\ConsolidationPaie;
use App\Entity\Document;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service de notifications pour le module Paie
 */
class PayrollNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Notifie l'éducateur quand son rapport de paie est validé
     */
    public function notifyReportValidated(ConsolidationPaie $consolidation): void
    {
        try {
            $user = $consolidation->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_my_payroll_show',
                ['id' => $consolidation->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject(sprintf('Votre rapport de paie %s est disponible', $consolidation->getPeriodLabel()))
                ->htmlTemplate('emails/payroll/report_validated.html.twig')
                ->context([
                    'consolidation' => $consolidation,
                    'user' => $user,
                    'validator' => $consolidation->getValidatedBy(),
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification rapport paie validé envoyée', [
                'consolidation_id' => $consolidation->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi notification rapport validé', [
                'consolidation_id' => $consolidation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifie l'admin que l'export a été envoyé au comptable
     */
    public function notifyExportSent(string $period, string $accountantEmail, User $admin): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($admin->getEmail(), $admin->getFullName()))
                ->subject(sprintf('Confirmation envoi export paie %s', $this->getPeriodLabel($period)))
                ->htmlTemplate('emails/payroll/export_sent.html.twig')
                ->context([
                    'period' => $period,
                    'period_label' => $this->getPeriodLabel($period),
                    'accountant_email' => $accountantEmail,
                    'admin' => $admin,
                    'sent_at' => new \DateTime(),
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Confirmation envoi export envoyée', [
                'period' => $period,
                'admin_id' => $admin->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi confirmation export', [
                'period' => $period,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifie l'éducateur quand sa fiche de paie est déposée
     */
    public function notifyPayslipDeposited(Document $payslip, User $user): void
    {
        try {
            if (!$user->getEmail()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_my_payslips',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject('Votre fiche de paie est disponible')
                ->htmlTemplate('emails/payroll/payslip_deposited.html.twig')
                ->context([
                    'payslip' => $payslip,
                    'user' => $user,
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification fiche de paie déposée envoyée', [
                'document_id' => $payslip->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi notification fiche de paie', [
                'document_id' => $payslip->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie un rappel aux admins si la validation n'est pas effectuée
     */
    public function sendValidationReminder(string $period, int $pendingCount): void
    {
        try {
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');

            if (empty($admins)) {
                $this->logger->warning('Aucun admin trouvé pour le rappel de validation', [
                    'period' => $period,
                ]);
                return;
            }

            $url = $this->urlGenerator->generate(
                'admin_payroll_month',
                ['period' => $period],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            foreach ($admins as $admin) {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                    ->to(new Address($admin->getEmail(), $admin->getFullName()))
                    ->subject(sprintf('Rappel : %d rapports de paie en attente de validation', $pendingCount))
                    ->htmlTemplate('emails/payroll/validation_reminder.html.twig')
                    ->context([
                        'period' => $period,
                        'period_label' => $this->getPeriodLabel($period),
                        'pending_count' => $pendingCount,
                        'admin' => $admin,
                        'url' => $url,
                        'currentYear' => date('Y'),
                    ]);

                $this->mailer->send($email);
            }

            $this->logger->info('Rappel validation envoyé', [
                'period' => $period,
                'pending_count' => $pendingCount,
                'admin_count' => count($admins),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi rappel validation', [
                'period' => $period,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie l'export au comptable par email
     */
    public function sendToAccountant(
        string $period,
        string $accountantEmail,
        string $csvContent,
        ?string $pdfContent,
        User $admin
    ): bool {
        try {
            $periodLabel = $this->getPeriodLabel($period);
            $filename = sprintf('export_paie_%s', str_replace('-', '_', $period));

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($accountantEmail))
                ->replyTo(new Address($admin->getEmail(), $admin->getFullName()))
                ->subject(sprintf('Export paie %s - RhNewLife', $periodLabel))
                ->htmlTemplate('emails/payroll/export_to_accountant.html.twig')
                ->context([
                    'period' => $period,
                    'period_label' => $periodLabel,
                    'admin' => $admin,
                    'currentYear' => date('Y'),
                ])
                ->attach($csvContent, $filename . '.csv', 'text/csv');

            if ($pdfContent) {
                $email->attach($pdfContent, $filename . '.pdf', 'application/pdf');
            }

            $this->mailer->send($email);

            // Notifier l'admin de la confirmation
            $this->notifyExportSent($period, $accountantEmail, $admin);

            $this->logger->info('Export envoyé au comptable', [
                'period' => $period,
                'accountant_email' => $accountantEmail,
                'admin_id' => $admin->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi export au comptable', [
                'period' => $period,
                'accountant_email' => $accountantEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notifie l'éducateur d'une correction sur son rapport
     */
    public function notifyReportCorrected(ConsolidationPaie $consolidation, string $field, string $comment): void
    {
        try {
            $user = $consolidation->getUser();

            if (!$user || !$user->getEmail()) {
                return;
            }

            // Ne notifier que si le rapport était déjà visible par l'éducateur
            if ($consolidation->isDraft()) {
                return;
            }

            $url = $this->urlGenerator->generate(
                'app_my_payroll_show',
                ['id' => $consolidation->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@rhnewlife.com', 'RH NewLife'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->replyTo(new Address('rh@rhnewlife.com', 'Service RH'))
                ->subject(sprintf('Correction sur votre rapport de paie %s', $consolidation->getPeriodLabel()))
                ->htmlTemplate('emails/payroll/report_corrected.html.twig')
                ->context([
                    'consolidation' => $consolidation,
                    'user' => $user,
                    'field' => $field,
                    'comment' => $comment,
                    'url' => $url,
                    'currentYear' => date('Y'),
                ]);

            $this->mailer->send($email);

            $this->logger->info('Notification correction rapport envoyée', [
                'consolidation_id' => $consolidation->getId(),
                'user_id' => $user->getId(),
                'field' => $field,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi notification correction', [
                'consolidation_id' => $consolidation->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Retourne le libellé d'une période
     */
    private function getPeriodLabel(string $period): string
    {
        $months = [
            '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
            '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre',
        ];

        $year = substr($period, 0, 4);
        $month = substr($period, 5, 2);

        return ($months[$month] ?? '') . ' ' . $year;
    }
}
