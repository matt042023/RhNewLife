<?php

namespace App\Command;

use App\Entity\TemplateContrat;
use App\Service\TemplateContratManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-wf09',
    description: 'Test WF09 implementation by creating a sample contract template',
)]
class TestWF09Command extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemplateContratManager $templateManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test de l\'implémentation WF09 - Gestion des Contrats');

        // Get admin user
        $adminUser = $this->entityManager->getRepository(\App\Entity\User::class)->find(1);

        if (!$adminUser) {
            $io->error('Aucun utilisateur admin trouvé avec ID 1');
            return Command::FAILURE;
        }

        $io->section('Création d\'un template de contrat CDI');

        $htmlContent = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Travail - CDI</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin: 20px 0; }
        .signature { margin-top: 60px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT DE TRAVAIL À DURÉE INDÉTERMINÉE</h1>
        <p><strong>RH NewLife</strong></p>
    </div>

    <div class="section">
        <h2>ENTRE LES SOUSSIGNÉS :</h2>
        <p><strong>RH NewLife</strong>, société spécialisée dans la gestion des ressources humaines</p>

        <p>Ci-après dénommée "l'Employeur"</p>

        <p><strong>ET :</strong></p>

        <p>M./Mme <strong>{{ employee.firstName }} {{ employee.lastName }}</strong></p>
        <p>Né(e) le : {{ employee.birthDate|date('d/m/Y') }}</p>
        <p>Matricule : {{ employee.matricule }}</p>
        <p>Email : {{ employee.email }}</p>

        <p>Ci-après dénommé(e) "le Salarié"</p>
    </div>

    <div class="section">
        <h2>ARTICLE 1 - ENGAGEMENT</h2>
        <p>L'Employeur engage le Salarié qui accepte, aux conditions suivantes :</p>

        <p><strong>Poste :</strong> {{ employee.position }}</p>
        <p><strong>Type de contrat :</strong> {{ contract.type }}</p>
        <p><strong>Date de début :</strong> {{ contract.startDate|date('d/m/Y') }}</p>
        {% if contract.endDate %}
        <p><strong>Date de fin :</strong> {{ contract.endDate|date('d/m/Y') }}</p>
        {% endif %}
    </div>

    <div class="section">
        <h2>ARTICLE 2 - RÉMUNÉRATION</h2>
        <p>Le salaire mensuel brut du Salarié est fixé à <strong>{{ contract.baseSalary }} €</strong>.</p>
    </div>

    <div class="section">
        <h2>ARTICLE 3 - DURÉE DU TRAVAIL</h2>
        <p>La durée hebdomadaire de travail est de <strong>{{ contract.weeklyHours }} heures</strong>.</p>
        {% if contract.activityRate %}
        <p>Taux d'activité : <strong>{{ contract.activityRate }}%</strong></p>
        {% endif %}
    </div>

    <div class="section">
        <h2>ARTICLE 4 - PÉRIODE D'ESSAI</h2>
        <p>Le présent contrat est conclu sous réserve d'une période d'essai de 3 mois, renouvelable une fois.</p>
    </div>

    <div class="signature">
        <table width="100%">
            <tr>
                <td width="50%">
                    <p><strong>L'Employeur</strong></p>
                    <p>Date : ________________</p>
                    <p>Signature :</p>
                    <br><br>
                </td>
                <td width="50%">
                    <p><strong>Le Salarié</strong></p>
                    <p>Date : {{ contract.signedAt ? contract.signedAt|date('d/m/Y') : '________________' }}</p>
                    <p>Signature électronique</p>
                    <br><br>
                </td>
            </tr>
        </table>
    </div>

    <div class="section" style="margin-top: 40px; font-size: 10px; color: #666;">
        <p>Document généré le {{ 'now'|date('d/m/Y à H:i') }}</p>
        {% if contract.signatureIp %}
        <p>Signé depuis l'adresse IP : {{ contract.signatureIp }}</p>
        {% endif %}
    </div>
</body>
</html>
HTML;

        try {
            $template = $this->templateManager->createTemplate([
                'name' => 'CDI - Contrat standard',
                'description' => 'Template de contrat CDI standard pour RH NewLife avec toutes les clauses de base',
                'contentHtml' => $htmlContent,
                'active' => true,
            ]);

            $io->success('Template de contrat créé avec succès !');
            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['ID', $template->getId()],
                    ['Nom', $template->getName()],
                    ['Créé par', $template->getCreatedBy() ? $template->getCreatedBy()->getEmail() : 'Admin (CLI)'],
                    ['Actif', $template->isActive() ? 'Oui' : 'Non'],
                    ['Variables valides', $template->hasValidVariables() ? 'Oui' : 'Non'],
                ]
            );

            $io->section('Variables utilisées dans le template :');
            $usedVars = $template->extractUsedVariables();
            $io->listing($usedVars);

            $io->section('Routes disponibles :');
            $io->table(
                ['Route', 'URL'],
                [
                    ['Liste des templates', '/admin/contract-templates'],
                    ['Créer un template', '/admin/contract-templates/create'],
                    ['Voir ce template', '/admin/contract-templates/' . $template->getId()],
                    ['Éditer ce template', '/admin/contract-templates/' . $template->getId() . '/edit'],
                    ['Aperçu', '/admin/contract-templates/' . $template->getId() . '/preview'],
                ]
            );

            $io->note('Vous pouvez maintenant accéder à ces routes dans votre navigateur pour tester l\'interface complète.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la création du template : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
