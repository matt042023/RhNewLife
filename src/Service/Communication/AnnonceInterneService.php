<?php

namespace App\Service\Communication;

use App\Entity\AnnonceInterne;
use App\Entity\User;
use App\Repository\AnnonceInterneRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AnnonceInterneService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnnonceInterneRepository $annonceRepository,
        private UserRepository $userRepository,
        private NotificationService $notificationService,
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
        private string $uploadDir
    ) {
    }

    /**
     * Create and publish announcement (WF77)
     */
    public function create(
        User $publiePar,
        string $titre,
        string $contenu,
        string $visibilite = AnnonceInterne::VISIBILITY_TOUS,
        bool $epingle = false,
        ?UploadedFile $image = null,
        ?int $expirationDays = 30
    ): AnnonceInterne {
        $annonce = new AnnonceInterne();
        $annonce->setPubliePar($publiePar);
        $annonce->setTitre($titre);
        $annonce->setContenu($contenu);
        $annonce->setVisibilite($visibilite);
        $annonce->setEpingle($epingle);

        if ($expirationDays !== null && $expirationDays > 0) {
            $annonce->setDateExpiration(
                (new \DateTime())->modify("+{$expirationDays} days")
            );
        } else {
            $annonce->setDateExpiration(null);
        }

        if ($image) {
            $filename = $this->uploadImage($image);
            $annonce->setImage($filename);
        }

        $this->entityManager->persist($annonce);
        $this->entityManager->flush();

        // Notify users based on visibility
        $this->notifyUsersOfAnnouncement($annonce);

        $this->logger->info('Announcement created', [
            'annonce_id' => $annonce->getId(),
            'publie_par' => $publiePar->getId(),
            'visibilite' => $visibilite,
            'titre' => $titre,
        ]);

        return $annonce;
    }

    /**
     * Update an existing announcement
     */
    public function update(
        AnnonceInterne $annonce,
        string $titre,
        string $contenu,
        string $visibilite,
        bool $epingle,
        ?UploadedFile $newImage = null,
        bool $removeImage = false,
        ?int $expirationDays = null
    ): AnnonceInterne {
        $annonce->setTitre($titre);
        $annonce->setContenu($contenu);
        $annonce->setVisibilite($visibilite);
        $annonce->setEpingle($epingle);

        if ($expirationDays !== null && $expirationDays > 0) {
            $annonce->setDateExpiration(
                (new \DateTime())->modify("+{$expirationDays} days")
            );
        }

        if ($removeImage && $annonce->getImage()) {
            $this->deleteImage($annonce->getImage());
            $annonce->setImage(null);
        }

        if ($newImage) {
            // Delete old image if exists
            if ($annonce->getImage()) {
                $this->deleteImage($annonce->getImage());
            }
            $filename = $this->uploadImage($newImage);
            $annonce->setImage($filename);
        }

        $this->entityManager->flush();

        $this->logger->info('Announcement updated', [
            'annonce_id' => $annonce->getId(),
            'titre' => $titre,
        ]);

        return $annonce;
    }

    /**
     * Notify users when announcement is published
     */
    private function notifyUsersOfAnnouncement(AnnonceInterne $annonce): void
    {
        $users = $this->getUsersForVisibility($annonce->getVisibilite());

        foreach ($users as $user) {
            // Don't notify the author
            if ($user === $annonce->getPubliePar()) {
                continue;
            }

            $this->notificationService->notifyAnnoncePublished(
                $user,
                $annonce->getId(),
                $annonce->getTitre()
            );
        }

        $this->logger->info('Users notified of announcement', [
            'annonce_id' => $annonce->getId(),
            'users_count' => count($users) - 1, // Minus the author
        ]);
    }

    /**
     * Get users based on visibility setting
     *
     * @return User[]
     */
    private function getUsersForVisibility(string $visibility): array
    {
        return match ($visibility) {
            AnnonceInterne::VISIBILITY_ADMIN => $this->userRepository->findByRole('ROLE_ADMIN'),
            AnnonceInterne::VISIBILITY_DIR => array_unique(
                array_merge(
                    $this->userRepository->findByRole('ROLE_ADMIN'),
                    $this->userRepository->findByRole('ROLE_DIRECTOR')
                ),
                SORT_REGULAR
            ),
            default => $this->userRepository->findAllActive(),
        };
    }

    /**
     * Upload image and return filename
     */
    private function uploadImage(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $filename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $targetDir = $this->uploadDir . '/annonces';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $filename);

        return 'annonces/' . $filename;
    }

    /**
     * Delete image file
     */
    private function deleteImage(string $imagePath): void
    {
        $fullPath = $this->uploadDir . '/' . $imagePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Deactivate/archive announcement
     */
    public function deactivate(AnnonceInterne $annonce): void
    {
        $annonce->deactivate();
        $this->entityManager->flush();

        $this->logger->info('Announcement deactivated', [
            'annonce_id' => $annonce->getId(),
        ]);
    }

    /**
     * Activate announcement
     */
    public function activate(AnnonceInterne $annonce): void
    {
        $annonce->activate();
        $this->entityManager->flush();

        $this->logger->info('Announcement activated', [
            'annonce_id' => $annonce->getId(),
        ]);
    }

    /**
     * Toggle pin status
     */
    public function togglePin(AnnonceInterne $annonce): bool
    {
        $annonce->toggleEpingle();
        $this->entityManager->flush();

        $this->logger->info('Announcement pin toggled', [
            'annonce_id' => $annonce->getId(),
            'epingle' => $annonce->isEpingle(),
        ]);

        return $annonce->isEpingle();
    }

    /**
     * Get active announcements for user
     *
     * @return AnnonceInterne[]
     */
    public function getActiveForUser(User $user, int $limit = 5): array
    {
        return $this->annonceRepository->findActiveForUser($user, $limit);
    }

    /**
     * Get all announcements for admin management
     *
     * @return AnnonceInterne[]
     */
    public function getAllPaginated(int $page = 1, int $limit = 20): array
    {
        return $this->annonceRepository->findAllPaginated($page, $limit);
    }

    /**
     * Get total count for pagination
     */
    public function getTotal(): int
    {
        return $this->annonceRepository->countAll();
    }

    /**
     * Delete announcement (including image)
     */
    public function delete(AnnonceInterne $annonce): void
    {
        if ($annonce->getImage()) {
            $this->deleteImage($annonce->getImage());
        }

        $this->entityManager->remove($annonce);
        $this->entityManager->flush();

        $this->logger->info('Announcement deleted', [
            'annonce_id' => $annonce->getId(),
        ]);
    }
}
