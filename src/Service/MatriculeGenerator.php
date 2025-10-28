<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de génération des matricules employés
 * Format: AAAA-#### (année + numéro séquentiel)
 */
class MatriculeGenerator
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Génère un nouveau matricule unique au format AAAA-####
     * AAAA = année courante
     * #### = numéro séquentiel sur 4 chiffres (paddé avec des zéros)
     */
    public function generate(): string
    {
        $year = date('Y');
        $nextSequence = $this->getNextSequence($year);

        return sprintf('%s-%04d', $year, $nextSequence);
    }

    /**
     * Valide le format d'un matricule
     */
    public function isValid(string $matricule): bool
    {
        // Format: AAAA-#### (4 chiffres tiret 4 chiffres)
        return (bool) preg_match('/^\d{4}-\d{4}$/', $matricule);
    }

    /**
     * Vérifie si un matricule est unique
     */
    public function isUnique(string $matricule): bool
    {
        $existing = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['matricule' => $matricule]);

        return $existing === null;
    }

    /**
     * Génère et assigne un matricule à un utilisateur
     */
    public function assignToUser(User $user): void
    {
        // Ne pas écraser un matricule existant
        if ($user->getMatricule() !== null) {
            return;
        }

        $matricule = $this->generate();
        $user->setMatricule($matricule);
    }

    /**
     * Obtient le prochain numéro de séquence pour une année donnée
     * Recherche le dernier matricule de l'année et incrémente
     */
    private function getNextSequence(string $year): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Trouve le dernier matricule de l'année
        $lastMatricule = $qb
            ->select('u.matricule')
            ->from(User::class, 'u')
            ->where('u.matricule LIKE :year')
            ->setParameter('year', $year . '-%')
            ->orderBy('u.matricule', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Si aucun matricule pour cette année, commence à 1
        if ($lastMatricule === null) {
            return 1;
        }

        // Extrait le numéro de séquence et incrémente
        $parts = explode('-', $lastMatricule['matricule']);
        $lastSequence = (int) $parts[1];

        return $lastSequence + 1;
    }

    /**
     * Extrait l'année d'un matricule
     */
    public function extractYear(string $matricule): ?string
    {
        if (!$this->isValid($matricule)) {
            return null;
        }

        return substr($matricule, 0, 4);
    }

    /**
     * Extrait le numéro de séquence d'un matricule
     */
    public function extractSequence(string $matricule): ?int
    {
        if (!$this->isValid($matricule)) {
            return null;
        }

        return (int) substr($matricule, 5);
    }
}
