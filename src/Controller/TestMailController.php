<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class TestMailController extends AbstractController
{
    #[Route('/test-mail', name: 'test_mail')]
    public function send(MailerInterface $mailer): Response
    {
        try {
            $email = (new Email())
                ->from('noreply@rhnewlife.local')
                ->to('test@demo.local')
                ->cc('manager@rhnewlife.local')
                ->subject('Test de MailHog - RhNewLife')
                ->html($this->getEmailTemplate());

            $mailer->send($email);

            return new Response(
                '<html><body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h1 style="color: #28a745;">✅ E-mail envoyé avec succès !</h1>
                    <p><strong>De:</strong> noreply@rhnewlife.local</p>
                    <p><strong>À:</strong> test@demo.local</p>
                    <p><strong>Cc:</strong> manager@rhnewlife.local</p>
                    <p><strong>Sujet:</strong> Test de MailHog - RhNewLife</p>
                    <hr>
                    <p>📧 Consultez l\'interface MailHog pour voir l\'e-mail :
                        <a href="http://localhost:8025" target="_blank">http://localhost:8025</a>
                    </p>
                    <p><a href="/test-mail">🔄 Envoyer un autre e-mail de test</a></p>
                </body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        } catch (\Exception $e) {
            return new Response(
                '<html><body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h1 style="color: #dc3545;">❌ Erreur lors de l\'envoi</h1>
                    <p><strong>Message d\'erreur:</strong></p>
                    <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px;">' .
                    htmlspecialchars($e->getMessage()) .
                    '</pre>
                    <p><a href="/test-mail">🔄 Réessayer</a></p>
                </body></html>',
                500,
                ['Content-Type' => 'text/html']
            );
        }
    }

    private function getEmailTemplate(): string
    {
        return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                    .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
                    .button { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    .info-box { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>✅ Test Email - RhNewLife</h1>
                    </div>
                    <div class="content">
                        <h2>Félicitations !</h2>
                        <p>Votre système d\'envoi d\'e-mails fonctionne correctement.</p>

                        <div class="info-box">
                            <strong>📋 Informations du test :</strong>
                            <ul>
                                <li><strong>Date:</strong> ' . date('d/m/Y H:i:s') . '</li>
                                <li><strong>Serveur:</strong> MailHog (Développement)</li>
                                <li><strong>Transport:</strong> SMTP</li>
                                <li><strong>Application:</strong> RhNewLife</li>
                            </ul>
                        </div>

                        <p>Ce message a été envoyé automatiquement par le système de gestion RH NewLife pour tester la configuration du service de messagerie.</p>

                        <a href="http://localhost:8025" class="button">📧 Voir dans MailHog</a>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' RhNewLife - Système de Gestion RH</p>
                        <p>Cet e-mail a été généré automatiquement, merci de ne pas y répondre.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
    }
}
