<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\PayrollNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/fiches-paie')]
#[IsGranted('ROLE_ADMIN')]
class PayslipController extends AbstractController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private PayrollNotificationService $notificationService,
        private SluggerInterface $slugger,
        private LoggerInterface $logger
    ) {
    }

    private function getUploadDir(): string
    {
        return $this->getParameter('kernel.project_dir') . '/var/uploads/payslips';
    }

    /**
     * Liste des fiches de paie déposées
     */
    #[Route('', name: 'admin_payslip_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $year = $request->query->get('year', date('Y'));
        $userId = $request->query->get('user');

        $criteria = ['type' => Document::TYPE_PAYSLIP];

        if ($userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $criteria['user'] = $user;
            }
        }

        $payslips = $this->documentRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC']
        );

        // Filtrer par année si spécifié
        if ($year) {
            $payslips = array_filter($payslips, function (Document $doc) use ($year) {
                return $doc->getCreatedAt()?->format('Y') === $year;
            });
        }

        $users = $this->userRepository->findActiveEducators();
        $years = $this->getAvailableYears();

        return $this->render('admin/payslip/index.html.twig', [
            'payslips' => $payslips,
            'users' => $users,
            'years' => $years,
            'selectedYear' => $year,
            'selectedUserId' => $userId,
        ]);
    }

    /**
     * Formulaire d'upload
     */
    #[Route('/deposer', name: 'admin_payslip_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $users = $this->userRepository->findActiveEducators();

        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user');
            $user = $this->userRepository->find($userId);

            if (!$user) {
                $this->addFlash('error', 'Utilisateur non trouvé.');
                return $this->redirectToRoute('admin_payslip_upload');
            }

            /** @var UploadedFile|null $file */
            $file = $request->files->get('file');

            if (!$file) {
                $this->addFlash('error', 'Aucun fichier sélectionné.');
                return $this->redirectToRoute('admin_payslip_upload');
            }

            // Vérifier le type de fichier
            if ($file->getMimeType() !== 'application/pdf') {
                $this->addFlash('error', 'Seuls les fichiers PDF sont acceptés.');
                return $this->redirectToRoute('admin_payslip_upload');
            }

            // Générer un nom de fichier sécurisé
            $period = $request->request->get('period', date('Y-m'));
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = sprintf(
                'fiche_paie_%s_%s_%s.%s',
                $user->getId(),
                $period,
                uniqid(),
                $file->guessExtension() ?? 'pdf'
            );

            try {
                $file->move($this->getUploadDir(), $newFilename);
            } catch (FileException $e) {
                $this->logger->error('Erreur upload fiche de paie', [
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                return $this->redirectToRoute('admin_payslip_upload');
            }

            // Créer le document
            $document = new Document();
            $document->setUser($user);
            $document->setType(Document::TYPE_PAYSLIP);
            $document->setFilename($newFilename);
            $document->setOriginalFilename($file->getClientOriginalName());
            $document->setStatus(Document::STATUS_VALIDATED);
            $document->setValidatedBy($this->getUser());
            $document->setValidatedAt(new \DateTime());

            // Stocker la période dans les métadonnées ou un champ dédié si disponible
            // Pour l'instant, on l'inclut dans le nom original
            $document->setOriginalFilename(sprintf('%s - %s', $period, $file->getClientOriginalName()));

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            // Envoyer la notification
            $this->notificationService->notifyPayslipDeposited($document, $user);

            $this->addFlash('success', sprintf(
                'Fiche de paie déposée pour %s.',
                $user->getFullName()
            ));

            return $this->redirectToRoute('admin_payslip_index');
        }

        return $this->render('admin/payslip/upload.html.twig', [
            'users' => $users,
            'currentPeriod' => date('Y-m'),
        ]);
    }

    /**
     * Upload en masse
     */
    #[Route('/deposer-masse', name: 'admin_payslip_bulk_upload', methods: ['GET', 'POST'])]
    public function bulkUpload(Request $request): Response
    {
        $users = $this->userRepository->findActiveEducators();

        if ($request->isMethod('POST')) {
            $period = $request->request->get('period', date('Y-m'));
            $files = $request->files->get('files', []);

            if (empty($files)) {
                $this->addFlash('error', 'Aucun fichier sélectionné.');
                return $this->redirectToRoute('admin_payslip_bulk_upload');
            }

            $successCount = 0;
            $errorCount = 0;

            foreach ($files as $file) {
                if (!$file instanceof UploadedFile || $file->getMimeType() !== 'application/pdf') {
                    $errorCount++;
                    continue;
                }

                // Essayer de trouver l'utilisateur par le nom du fichier
                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $user = $this->findUserByFilename($filename, $users);

                if (!$user) {
                    $this->logger->warning('Utilisateur non trouvé pour le fichier', [
                        'filename' => $file->getClientOriginalName(),
                    ]);
                    $errorCount++;
                    continue;
                }

                // Upload du fichier
                $newFilename = sprintf(
                    'fiche_paie_%s_%s_%s.%s',
                    $user->getId(),
                    $period,
                    uniqid(),
                    $file->guessExtension() ?? 'pdf'
                );

                try {
                    $file->move($this->getUploadDir(), $newFilename);
                } catch (FileException $e) {
                    $errorCount++;
                    continue;
                }

                // Créer le document
                $document = new Document();
                $document->setUser($user);
                $document->setType(Document::TYPE_PAYSLIP);
                $document->setFilename($newFilename);
                $document->setOriginalFilename(sprintf('%s - %s', $period, $file->getClientOriginalName()));
                $document->setStatus(Document::STATUS_VALIDATED);
                $document->setValidatedBy($this->getUser());
                $document->setValidatedAt(new \DateTime());

                $this->entityManager->persist($document);

                // Notifier l'utilisateur
                $this->notificationService->notifyPayslipDeposited($document, $user);

                $successCount++;
            }

            $this->entityManager->flush();

            if ($successCount > 0) {
                $this->addFlash('success', sprintf('%d fiche(s) de paie déposée(s) avec succès.', $successCount));
            }

            if ($errorCount > 0) {
                $this->addFlash('warning', sprintf('%d fichier(s) n\'ont pas pu être traités.', $errorCount));
            }

            return $this->redirectToRoute('admin_payslip_index');
        }

        return $this->render('admin/payslip/bulk_upload.html.twig', [
            'users' => $users,
            'currentPeriod' => date('Y-m'),
        ]);
    }

    /**
     * Supprimer une fiche de paie
     */
    #[Route('/{id}/supprimer', name: 'admin_payslip_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        if ($document->getType() !== Document::TYPE_PAYSLIP) {
            throw $this->createNotFoundException('Document non trouvé');
        }

        if (!$this->isCsrfTokenValid('delete' . $document->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_payslip_index');
        }

        // Supprimer le fichier physique
        $filepath = $this->getUploadDir() . '/' . $document->getFilename();
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Fiche de paie supprimée.');

        return $this->redirectToRoute('admin_payslip_index');
    }

    /**
     * Retourne les années disponibles
     */
    private function getAvailableYears(): array
    {
        $currentYear = (int) date('Y');
        $years = [];

        for ($i = 0; $i < 5; $i++) {
            $years[] = $currentYear - $i;
        }

        return $years;
    }

    /**
     * Essaye de trouver un utilisateur par le nom de fichier
     */
    private function findUserByFilename(string $filename, array $users): ?User
    {
        $filename = strtolower($filename);

        foreach ($users as $user) {
            $fullName = strtolower($user->getLastName() . ' ' . $user->getFirstName());
            $reverseName = strtolower($user->getFirstName() . ' ' . $user->getLastName());
            $matricule = strtolower($user->getMatricule() ?? '');

            if (
                str_contains($filename, strtolower($user->getLastName())) ||
                str_contains($filename, $matricule)
            ) {
                return $user;
            }
        }

        return null;
    }
}
