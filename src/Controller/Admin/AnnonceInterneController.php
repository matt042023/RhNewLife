<?php

namespace App\Controller\Admin;

use App\Entity\AnnonceInterne;
use App\Entity\User;
use App\Form\AnnonceInterneType;
use App\Repository\AnnonceInterneRepository;
use App\Service\Communication\AnnonceInterneService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/annonces')]
#[IsGranted('ROLE_ADMIN')]
class AnnonceInterneController extends AbstractController
{
    public function __construct(
        private AnnonceInterneRepository $repository,
        private AnnonceInterneService $service
    ) {
    }

    /**
     * List all announcements
     */
    #[Route('', name: 'admin_annonce_interne_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $annonces = $this->repository->findAllPaginated($page, $limit);
        $total = $this->repository->countAll();
        $totalPages = (int) ceil($total / $limit);

        return $this->render('admin/annonce_interne/index.html.twig', [
            'annonces' => $annonces,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    /**
     * Create new announcement
     */
    #[Route('/new', name: 'admin_annonce_interne_new', methods: ['GET', 'POST'])]
    public function new(#[CurrentUser] User $user, Request $request): Response
    {
        $form = $this->createForm(AnnonceInterneType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Get uploaded file from unmapped field
            $imageFile = $form->get('image')->getData();

            $this->service->create(
                $user,
                $data['titre'],
                $data['contenu'],
                $data['visibilite'] ?? AnnonceInterne::VISIBILITY_TOUS,
                $data['epingle'] ?? false,
                $imageFile,
                $data['expirationDays'] ?? 30
            );

            $this->addFlash('success', 'Annonce publiee avec succes');
            return $this->redirectToRoute('admin_annonce_interne_index');
        }

        return $this->render('admin/annonce_interne/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * View announcement
     */
    #[Route('/{id}', name: 'admin_annonce_interne_show', methods: ['GET'])]
    public function show(AnnonceInterne $annonce): Response
    {
        return $this->render('admin/annonce_interne/show.html.twig', [
            'annonce' => $annonce,
        ]);
    }

    /**
     * Edit announcement
     */
    #[Route('/{id}/edit', name: 'admin_annonce_interne_edit', methods: ['GET', 'POST'])]
    public function edit(AnnonceInterne $annonce, Request $request): Response
    {
        $form = $this->createForm(AnnonceInterneType::class, [
            'titre' => $annonce->getTitre(),
            'contenu' => $annonce->getContenu(),
            'visibilite' => $annonce->getVisibilite(),
            'epingle' => $annonce->isEpingle(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Get uploaded file from unmapped field
            $imageFile = $form->get('image')->getData();

            $this->service->update(
                $annonce,
                $data['titre'],
                $data['contenu'],
                $data['visibilite'],
                $data['epingle'] ?? false,
                $imageFile,
                $request->request->getBoolean('remove_image'),
                $data['expirationDays'] ?? null
            );

            $this->addFlash('success', 'Annonce modifiee avec succes');
            return $this->redirectToRoute('admin_annonce_interne_index');
        }

        return $this->render('admin/annonce_interne/edit.html.twig', [
            'annonce' => $annonce,
            'form' => $form,
        ]);
    }

    /**
     * Toggle pin status
     */
    #[Route('/{id}/toggle-pin', name: 'admin_annonce_interne_toggle_pin', methods: ['POST'])]
    public function togglePin(AnnonceInterne $annonce, Request $request): Response
    {
        $newStatus = $this->service->togglePin($annonce);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'epingle' => $newStatus]);
        }

        $this->addFlash('success', $newStatus ? 'Annonce epinglee' : 'Annonce desepinglee');
        return $this->redirectToRoute('admin_annonce_interne_index');
    }

    /**
     * Deactivate announcement
     */
    #[Route('/{id}/deactivate', name: 'admin_annonce_interne_deactivate', methods: ['POST'])]
    public function deactivate(AnnonceInterne $annonce, Request $request): Response
    {
        $this->service->deactivate($annonce);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Annonce desactivee');
        return $this->redirectToRoute('admin_annonce_interne_index');
    }

    /**
     * Activate announcement
     */
    #[Route('/{id}/activate', name: 'admin_annonce_interne_activate', methods: ['POST'])]
    public function activate(AnnonceInterne $annonce, Request $request): Response
    {
        $this->service->activate($annonce);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Annonce activee');
        return $this->redirectToRoute('admin_annonce_interne_index');
    }

    /**
     * Delete announcement
     */
    #[Route('/{id}/delete', name: 'admin_annonce_interne_delete', methods: ['POST'])]
    public function delete(AnnonceInterne $annonce, Request $request): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('delete' . $annonce->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_annonce_interne_index');
        }

        $this->service->delete($annonce);

        $this->addFlash('success', 'Annonce supprimee');
        return $this->redirectToRoute('admin_annonce_interne_index');
    }
}
