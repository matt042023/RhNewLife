<?php

namespace App\Controller\Admin;

use App\Entity\Villa;
use App\Repository\VillaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/villas')]
#[IsGranted('ROLE_ADMIN')]
class VillaController extends AbstractController
{
    public function __construct(
        private VillaRepository $villaRepository
    ) {
    }

    #[Route('', name: 'app_admin_villas_list', methods: ['GET'])]
    public function list(): Response
    {
        $this->denyAccessUnlessGranted('VILLA_LIST');

        $villas = $this->villaRepository->findAll();

        return $this->render('admin/villas/list.html.twig', [
            'villas' => $villas,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_villas_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function view(Villa $villa): Response
    {
        $this->denyAccessUnlessGranted('VILLA_VIEW', $villa);

        $activeUsers = $villa->getUsers()->filter(function($user) {
            return $user->getStatus() === \App\Entity\User::STATUS_ACTIVE;
        });

        return $this->render('admin/villas/view.html.twig', [
            'villa' => $villa,
            'activeUsers' => $activeUsers,
            'canDelete' => $villa->canBeDeleted(),
        ]);
    }

    #[Route('/create', name: 'app_admin_villas_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('VILLA_CREATE');

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $color = $request->request->get('color');

            $errors = [];

            // Validation
            if (!$nom || strlen(trim($nom)) === 0) {
                $errors[] = 'Le nom de la villa est obligatoire.';
            }

            if (strlen($nom) > 255) {
                $errors[] = 'Le nom ne peut pas dépasser 255 caractères.';
            }

            if ($color && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $errors[] = 'Le code couleur doit être au format hexadécimal (#RRGGBB).';
            }

            $existing = $this->villaRepository->findOneBy(['nom' => $nom]);
            if ($existing) {
                $errors[] = 'Une villa avec ce nom existe déjà.';
            }

            if (empty($errors)) {
                try {
                    $villa = new Villa();
                    $villa->setNom($nom);

                    if ($color) {
                        $villa->setColor($color);
                    }

                    $this->villaRepository->save($villa, true);

                    $this->addFlash('success', 'Villa créée avec succès.');
                    return $this->redirectToRoute('app_admin_villas_view', ['id' => $villa->getId()]);
                } catch (\Exception $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }

            return $this->render('admin/villas/create.html.twig', [
                'errors' => $errors,
                'formData' => $request->request->all(),
            ]);
        }

        return $this->render('admin/villas/create.html.twig', [
            'errors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_villas_edit', methods: ['GET', 'POST'])]
    public function edit(Villa $villa, Request $request): Response
    {
        $this->denyAccessUnlessGranted('VILLA_EDIT', $villa);

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $color = $request->request->get('color');

            $errors = [];

            if (!$nom || strlen(trim($nom)) === 0) {
                $errors[] = 'Le nom de la villa est obligatoire.';
            }

            if (strlen($nom) > 255) {
                $errors[] = 'Le nom ne peut pas dépasser 255 caractères.';
            }

            if ($color && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $errors[] = 'Le code couleur doit être au format hexadécimal (#RRGGBB).';
            }

            $existing = $this->villaRepository->findOneBy(['nom' => $nom]);
            if ($existing && $existing->getId() !== $villa->getId()) {
                $errors[] = 'Une autre villa avec ce nom existe déjà.';
            }

            if (empty($errors)) {
                try {
                    $villa->setNom($nom);
                    $villa->setColor($color ?: null);

                    $this->villaRepository->save($villa, true);

                    $this->addFlash('success', 'Villa modifiée avec succès.');
                    return $this->redirectToRoute('app_admin_villas_view', ['id' => $villa->getId()]);
                } catch (\Exception $e) {
                    $errors[] = 'Erreur : ' . $e->getMessage();
                }
            }

            return $this->render('admin/villas/edit.html.twig', [
                'villa' => $villa,
                'errors' => $errors,
            ]);
        }

        return $this->render('admin/villas/edit.html.twig', [
            'villa' => $villa,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_villas_delete', methods: ['POST'])]
    public function delete(Villa $villa): Response
    {
        $this->denyAccessUnlessGranted('VILLA_DELETE', $villa);

        try {
            if (!$villa->canBeDeleted()) {
                $this->addFlash('error', 'Impossible de supprimer cette villa : des utilisateurs ou affectations y sont liés.');
                return $this->redirectToRoute('app_admin_villas_view', ['id' => $villa->getId()]);
            }

            $this->villaRepository->remove($villa, true);
            $this->addFlash('success', 'Villa supprimée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_villas_list');
    }
}
