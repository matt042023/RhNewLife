<?php

namespace App\Service\SqueletteGarde;

use App\Entity\SqueletteGarde;
use App\Entity\User;
use App\Repository\SqueletteGardeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SqueletteGardeManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private SqueletteGardeRepository $repository,
        private SqueletteGardeValidator $validator,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a new template
     */
    public function createSquelette(
        string $nom,
        ?User $createdBy = null,
        ?string $description = null,
        array $configuration = []
    ): SqueletteGarde {
        // Validate
        $this->validator->validateName($nom);
        $this->validator->validateConfiguration($configuration);

        $squelette = new SqueletteGarde();
        $squelette
            ->setNom($nom)
            ->setDescription($description)
            ->setCreatedBy($createdBy);

        if (!empty($configuration)) {
            $squelette->setConfigurationArray($configuration);
        }

        $this->em->persist($squelette);
        $this->em->flush();

        $this->logger->info('SqueletteGarde created', [
            'id' => $squelette->getId(),
            'nom' => $nom,
        ]);

        return $squelette;
    }

    /**
     * Update an existing template
     */
    public function updateSquelette(
        SqueletteGarde $squelette,
        string $nom,
        ?string $description,
        array $configuration,
        ?User $updatedBy = null
    ): void {
        // Validate
        $this->validator->validateName($nom, $squelette->getId());
        $this->validator->validateConfiguration($configuration);

        $squelette
            ->setNom($nom)
            ->setDescription($description)
            ->setConfigurationArray($configuration)
            ->setUpdatedBy($updatedBy);

        $this->em->flush();

        $this->logger->info('SqueletteGarde updated', ['id' => $squelette->getId()]);
    }

    /**
     * Delete a template
     */
    public function deleteSquelette(SqueletteGarde $squelette): void
    {
        $this->em->remove($squelette);
        $this->em->flush();

        $this->logger->info('SqueletteGarde deleted', ['id' => $squelette->getId()]);
    }

    /**
     * Duplicate a template
     */
    public function duplicateSquelette(
        SqueletteGarde $source,
        string $newNom,
        ?User $createdBy = null
    ): SqueletteGarde {
        $this->validator->validateName($newNom);

        $duplicate = new SqueletteGarde();
        $duplicate
            ->setNom($newNom)
            ->setDescription($source->getDescription())
            ->setConfigurationArray($source->getConfigurationArray())
            ->setCreatedBy($createdBy);

        $this->em->persist($duplicate);
        $this->em->flush();

        $this->logger->info('SqueletteGarde duplicated', [
            'source_id' => $source->getId(),
            'new_id' => $duplicate->getId(),
        ]);

        return $duplicate;
    }

    /**
     * Record usage when template is applied to a planning
     */
    public function recordUsage(SqueletteGarde $squelette): void
    {
        $squelette->incrementUtilisation();
        $this->em->flush();
    }
}
