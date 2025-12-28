<?php

namespace App\Service;

use App\Entity\TemplateContrat;
use App\Entity\User;
use App\Repository\TemplateContratRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service de gestion des templates de contrat
 * Responsabilités:
 * - CRUD templates
 * - Validation contenu HTML
 * - Gestion activation/désactivation
 */
class TemplateContratManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemplateContratRepository $templateRepository,
        private Security $security
    ) {}

    /**
     * Crée un nouveau template
     */
    public function createTemplate(array $data): TemplateContrat
    {
        $template = new TemplateContrat();
        $this->populateTemplate($template, $data);

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $template->setCreatedBy($currentUser);
            $template->setModifiedBy($currentUser);
        }

        // Valider le contenu HTML
        $errors = $this->validateHtmlContent($template->getContentHtml());
        if (!empty($errors)) {
            throw new \RuntimeException('Le contenu HTML contient des erreurs: ' . implode(', ', $errors));
        }

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $template;
    }

    /**
     * Met à jour un template existant
     */
    public function updateTemplate(TemplateContrat $template, array $data): void
    {
        $this->populateTemplate($template, $data);

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $template->setModifiedBy($currentUser);
        }

        // Valider le contenu HTML
        if (isset($data['contentHtml'])) {
            $errors = $this->validateHtmlContent($template->getContentHtml());
            if (!empty($errors)) {
                throw new \RuntimeException('Le contenu HTML contient des erreurs: ' . implode(', ', $errors));
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Désactive un template
     */
    public function deactivateTemplate(TemplateContrat $template): void
    {
        $template->setActive(false);

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $template->setModifiedBy($currentUser);
        }

        $this->entityManager->flush();
    }

    /**
     * Active un template
     */
    public function activateTemplate(TemplateContrat $template): void
    {
        $template->setActive(true);

        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $template->setModifiedBy($currentUser);
        }

        $this->entityManager->flush();
    }

    /**
     * Retourne les templates actifs
     */
    public function getActiveTemplates(): array
    {
        return $this->templateRepository->findActiveTemplates();
    }

    /**
     * Recherche templates par nom ou description
     */
    public function searchTemplates(string $query): array
    {
        return $this->templateRepository->searchByName($query);
    }

    /**
     * Valide le contenu HTML d'un template
     * Retourne un tableau d'erreurs (vide si valide)
     */
    public function validateHtmlContent(?string $html): array
    {
        $errors = [];

        if (empty($html)) {
            $errors[] = 'Le contenu HTML ne peut pas être vide';
            return $errors;
        }

        // Extraire les variables utilisées
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $html, $matches);
        $usedVariables = array_unique($matches[0]);

        // Vérifier que les variables utilisées sont valides
        $validVariables = TemplateContrat::getValidVariablesList();

        foreach ($usedVariables as $var) {
            if (!in_array($var, $validVariables)) {
                $errors[] = "Variable invalide: $var";
            }
        }

        // Vérification HTML basique (pas de scripts)
        if (preg_match('/<script[\s>]/i', $html)) {
            $errors[] = 'Les balises <script> ne sont pas autorisées pour des raisons de sécurité';
        }

        // Vérification des balises PHP
        if (preg_match('/<\?php/i', $html)) {
            $errors[] = 'Le code PHP n\'est pas autorisé dans les templates';
        }

        return $errors;
    }

    /**
     * Extrait les variables utilisées dans un template
     */
    public function extractVariablesFromHtml(string $html): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $html, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Retourne des statistiques sur un template
     */
    public function getTemplateStats(TemplateContrat $template): array
    {
        return [
            'usedVariables' => $template->extractUsedVariables(),
            'invalidVariables' => $template->getInvalidVariables(),
            'contractCount' => $this->templateRepository->countContractsUsingTemplate($template),
            'isValid' => $template->hasValidVariables(),
            'htmlLength' => strlen($template->getContentHtml() ?? ''),
        ];
    }

    /**
     * Vérifie si un template peut être supprimé (désactivé)
     */
    public function canBeDeactivated(TemplateContrat $template): bool
    {
        // Un template peut être désactivé même s'il a des contrats associés
        // Les contrats conservent la référence au template
        return $template->isActive();
    }

    /**
     * Remplit un template avec les données fournies
     */
    private function populateTemplate(TemplateContrat $template, array $data): void
    {
        if (isset($data['name'])) {
            $template->setName($data['name']);
        }

        if (isset($data['description'])) {
            $template->setDescription($data['description']);
        }

        if (isset($data['contentHtml'])) {
            $template->setContentHtml($data['contentHtml']);
        }

        if (isset($data['active'])) {
            $template->setActive((bool) $data['active']);
        }
    }

    /**
     * Génère un template d'exemple pour démonstration
     */
    public function generateExampleTemplate(): string
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; padding: 40px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1>CONTRAT DE TRAVAIL</h1>
        <h2>{{ contract.type }}</h2>
    </div>

    <div style="margin-bottom: 30px;">
        <h3>Entre les soussignés :</h3>
        <p>
            <strong>RH NewLife</strong>, association loi 1901<br>
            Représentée par son directeur<br>
        </p>
        <p style="text-align: center;"><strong>D'une part,</strong></p>
        <p style="margin-top: 20px;">
            <strong>Et :</strong><br>
            Monsieur/Madame <strong>{{ employee.fullName }}</strong><br>
            Né(e) le {{ employee.birthDate }}<br>
            Domicilié(e) : {{ employee.address }}, {{ employee.postalCode }} {{ employee.city }}<br>
        </p>
        <p style="text-align: center;"><strong>D'autre part,</strong></p>
    </div>

    <div style="margin-bottom: 30px;">
        <h3>Il a été convenu ce qui suit :</h3>

        <h4>Article 1 - Engagement</h4>
        <p>
            L'employeur engage {{ employee.firstName }} {{ employee.lastName }}
            en qualité de <strong>{{ employee.position }}</strong>
            à compter du <strong>{{ contract.startDate }}</strong>
            {% if contract.endDate %}jusqu'au {{ contract.endDate }}{% endif %}.
        </p>

        <h4>Article 2 - Rémunération</h4>
        <p>
            La rémunération brute mensuelle est fixée à <strong>{{ contract.baseSalary }} €</strong>
            pour un taux d'activité de {{ contract.activityRate }}
            ({{ contract.weeklyHours }} heures hebdomadaires).
        </p>

        <h4>Article 3 - Lieu de travail</h4>
        <p>
            Le salarié exercera ses fonctions principalement à : <strong>{{ contract.villa }}</strong>
        </p>

        <h4>Article 4 - Période d'essai</h4>
        <p>
            {% if contract.essaiEndDate %}
            Le présent contrat est conclu avec une période d'essai jusqu'au {{ contract.essaiEndDate }}.
            {% else %}
            Aucune période d'essai n'est prévue.
            {% endif %}
        </p>

        <h4>Article 5 - Horaires de travail</h4>
        <p>
            Jours travaillés : {{ contract.workingDaysFormatted }}
        </p>
    </div>

    <div style="margin-top: 60px;">
        <p>Fait en double exemplaire</p>
        <p>Le {{ currentDate }}</p>

        <div style="display: flex; justify-content: space-between; margin-top: 80px;">
            <div>
                <p><strong>L'employeur</strong></p>
                <p style="margin-top: 60px;">__________________________</p>
            </div>
            <div>
                <p><strong>Le salarié</strong></p>
                <p><strong>{{ employee.fullName }}</strong></p>
                <p style="margin-top: 60px;">__________________________</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
