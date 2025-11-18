<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\TemplateContrat;
use App\Entity\User;
use Twig\Environment;

/**
 * Service de génération de contrats depuis templates
 * Responsabilités:
 * - Générer HTML depuis template avec remplacement variables
 * - Générer PDF depuis HTML
 * - Gestion stockage fichiers
 */
class ContractGeneratorService
{
    private string $uploadsDir;

    public function __construct(
        private Environment $twig,
        string $projectDir
    ) {
        $this->uploadsDir = $projectDir . '/public/uploads';
    }

    /**
     * Génère un brouillon de contrat (HTML + PDF) depuis un template
     * Retourne le chemin relatif du PDF généré
     */
    public function generateDraftContract(Contract $contract, TemplateContrat $template): string
    {
        // Générer le HTML avec variables remplacées
        $html = $this->generateHtmlFromTemplate($template, $contract, $contract->getUser());

        // Générer le PDF depuis le HTML
        $filename = sprintf(
            'contract_draft_%d_%s.pdf',
            $contract->getId() ?? uniqid(),
            date('YmdHis')
        );

        $pdfPath = $this->generatePdfFromHtml($html, $filename, 'contracts/drafts');

        return $pdfPath;
    }

    /**
     * Génère le HTML du contrat avec variables remplacées
     */
    public function generateHtmlFromTemplate(
        TemplateContrat $template,
        Contract $contract,
        User $employee
    ): string {
        // Préparer les données pour le template
        $data = $this->prepareTemplateData($contract, $employee);

        // Récupérer le contenu HTML du template
        $templateHtml = $template->getContentHtml();

        // Remplacer les variables
        $html = $this->replaceVariables($templateHtml, $data);

        return $html;
    }

    /**
     * Remplace les variables {{ variable }} dans le HTML
     * Utilise Twig pour un remplacement sécurisé
     */
    private function replaceVariables(string $html, array $data): string
    {
        try {
            // Créer un template Twig temporaire depuis la chaîne
            $twigTemplate = $this->twig->createTemplate($html);

            // Rendre le template avec les données
            return $twigTemplate->render($data);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Erreur lors du remplacement des variables: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Prépare les données pour le remplacement des variables
     */
    private function prepareTemplateData(Contract $contract, User $employee): array
    {
        return [
            'employee' => [
                'firstName' => $employee->getFirstName(),
                'lastName' => $employee->getLastName(),
                'fullName' => $employee->getFullName(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhone(),
                'address' => $employee->getAddress(),
                'matricule' => $employee->getMatricule(),
                'position' => $employee->getPosition(),
                'structure' => $employee->getStructure(),
                'familyStatus' => $employee->getFamilyStatus(),
                'children' => $employee->getChildren(),
                'iban' => $employee->getIban(),
                'bic' => $employee->getBic(),
                'hiringDate' => $employee->getHiringDate(),
            ],
            'contract' => [
                'type' => $contract->getTypeLabel(),
                'startDate' => $contract->getStartDate(),
                'endDate' => $contract->getEndDate(),
                'baseSalary' => $contract->getBaseSalary(),
                'weeklyHours' => $contract->getWeeklyHours(),
                'activityRate' => $contract->getActivityRate(),
                'villa' => $contract->getVilla(),
                'essaiEndDate' => $contract->getEssaiEndDate(),
                'workingDaysFormatted' => $contract->getWorkingDaysFormatted(),
                'signedAt' => $contract->getSignedAt(),
                'signatureIp' => $contract->getSignatureIp(),
            ],
            'currentDate' => new \DateTime(),
            'currentYear' => (new \DateTime())->format('Y'),
        ];
    }

    /**
     * Génère un PDF depuis du HTML et le sauvegarde
     * Retourne le chemin relatif du fichier
     *
     * NOTE: Cette méthode utilise un placeholder pour le moment.
     * Dans une implémentation complète, utilisez Dompdf ou Snappy.
     */
    public function generatePdfFromHtml(
        string $html,
        string $filename,
        string $subfolder = 'contracts'
    ): string {
        // Créer le répertoire si nécessaire
        $targetDir = $this->uploadsDir . '/' . $subfolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $fullPath = $targetDir . '/' . $filename;
        $relativePath = $subfolder . '/' . $filename;

        // Générer un HTML complet pour le PDF
        $pdfHtml = $this->wrapHtmlForPdf($html);

        // Générer le PDF avec Dompdf
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($pdfHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Sauvegarder le PDF
        file_put_contents($fullPath, $dompdf->output());

        return $relativePath;
    }

    /**
     * Enveloppe le HTML dans une structure complète pour PDF
     */
    private function wrapHtmlForPdf(string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat de travail</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }
        h1, h2, h3, h4 {
            color: #2c3e50;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        h1 { font-size: 24pt; }
        h2 { font-size: 18pt; }
        h3 { font-size: 14pt; }
        h4 { font-size: 12pt; }
        p {
            margin: 10px 0;
            text-align: justify;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        .signature-block {
            margin-top: 60px;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    {$content}

    <div class="footer">
        <p>Document généré le {{ currentDate }}</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Génère un PDF signé avec bloc de signature
     */
    public function generateSignedPdf(Contract $contract, array $signatureData): string
    {
        // Récupérer le HTML original
        if (!$contract->getTemplate()) {
            throw new \RuntimeException('Le contrat n\'a pas de template associé');
        }

        $html = $this->generateHtmlFromTemplate(
            $contract->getTemplate(),
            $contract,
            $contract->getUser()
        );

        // Ajouter le bloc de signature
        $signatureBlock = $this->generateSignatureBlock($signatureData);
        $html .= $signatureBlock;

        // Générer le PDF
        $filename = sprintf(
            'contract_signed_%d_%s.pdf',
            $contract->getId(),
            date('YmdHis')
        );

        $pdfPath = $this->generatePdfFromHtml($html, $filename, 'contracts/signed');

        return $pdfPath;
    }

    /**
     * Génère le bloc de signature HTML
     */
    private function generateSignatureBlock(array $signatureData): string
    {
        $signedAt = $signatureData['signedAt'] ?? new \DateTime();
        $employeeName = $signatureData['employeeName'] ?? '';
        $ip = $signatureData['ip'] ?? '';
        $userAgent = $signatureData['userAgent'] ?? '';
        $documentHash = $signatureData['documentHash'] ?? '';

        return <<<HTML

<div class="signature-block" style="margin-top: 60px; border-top: 2px solid #ccc; padding-top: 20px;">
    <h3>Signature électronique</h3>
    <p><strong>Document signé électroniquement</strong></p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Signataire :</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd;">{$employeeName}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Date et heure :</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd;">{$signedAt->format('d/m/Y à H:i:s')}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Adresse IP :</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd;">{$ip}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd;"><strong>Empreinte document :</strong></td>
            <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace; font-size: 9pt;">{$documentHash}</td>
        </tr>
    </table>

    <p style="margin-top: 30px; font-size: 9pt; color: #666;">
        Ce document a été signé électroniquement conformément au règlement eIDAS (UE) n°910/2014.
        La signature électronique a la même valeur juridique qu'une signature manuscrite.
    </p>
</div>
HTML;
    }

    /**
     * Calcule le hash SHA256 d'un contenu pour empreinte
     */
    public function calculateDocumentHash(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Vérifie si les répertoires d'upload existent et les crée si nécessaire
     */
    public function ensureUploadDirectories(): void
    {
        $dirs = [
            $this->uploadsDir . '/contracts',
            $this->uploadsDir . '/contracts/drafts',
            $this->uploadsDir . '/contracts/signed',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
