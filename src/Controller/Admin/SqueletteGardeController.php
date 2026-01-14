<?php

namespace App\Controller\Admin;

use App\Entity\SqueletteGarde;
use App\Repository\SqueletteGardeRepository;
use App\Repository\VillaRepository;
use App\Service\SqueletteGarde\SqueletteGardeManager;
use App\Service\SqueletteGarde\SqueletteGardeValidator;
use App\Service\SqueletteGarde\SqueletteGardeValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/templates-garde')]
#[IsGranted('ROLE_ADMIN')]
class SqueletteGardeController extends AbstractController
{
    public function __construct(
        private SqueletteGardeRepository $repository,
        private SqueletteGardeManager $manager,
        private SqueletteGardeValidator $validator,
        private VillaRepository $villaRepository
    ) {}

    /**
     * List all templates
     */
    #[Route('', name: 'admin_squelette_garde_index', methods: ['GET'])]
    public function index(): Response
    {
        $templates = $this->repository->findAllTemplates();
        $stats = $this->repository->getStats();

        return $this->render('admin/squelette_garde/index.html.twig', [
            'templates' => $templates,
            'stats' => $stats,
        ]);
    }

    /**
     * Show template builder/editor (FullCalendar interface)
     */
    #[Route('/builder/{id}', name: 'admin_squelette_garde_builder', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[Route('/builder', name: 'admin_squelette_garde_builder_new', methods: ['GET'])]
    public function builder(?SqueletteGarde $squelette = null): Response
    {
        $villas = $this->villaRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/squelette_garde/builder.html.twig', [
            'squelette' => $squelette,
            'isEdit' => $squelette !== null,
            'villas' => $villas,
        ]);
    }

    /**
     * Delete template
     */
    #[Route('/{id}/supprimer', name: 'admin_squelette_garde_delete', methods: ['POST'])]
    public function delete(Request $request, SqueletteGarde $squelette): Response
    {
        if ($this->isCsrfTokenValid('delete' . $squelette->getId(), $request->request->get('_token'))) {
            try {
                $this->manager->deleteSquelette($squelette);
                $this->addFlash('success', 'Template supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_squelette_garde_index');
    }

    /**
     * Duplicate template
     */
    #[Route('/{id}/dupliquer', name: 'admin_squelette_garde_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, SqueletteGarde $squelette): Response
    {
        if ($this->isCsrfTokenValid('duplicate' . $squelette->getId(), $request->request->get('_token'))) {
            try {
                $newNom = $squelette->getNom() . ' (copie)';
                $duplicate = $this->manager->duplicateSquelette($squelette, $newNom, $this->getUser());

                $this->addFlash('success', 'Template dupliqué avec succès.');
                return $this->redirectToRoute('admin_squelette_garde_builder', ['id' => $duplicate->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_squelette_garde_index');
    }

    // ========== REST API ENDPOINTS ==========

    /**
     * API: Get template data
     */
    #[Route('/api/{id}', name: 'api_squelette_garde_get', methods: ['GET'])]
    public function apiGet(SqueletteGarde $squelette): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $squelette->getId(),
                'nom' => $squelette->getNom(),
                'description' => $squelette->getDescription(),
                'configuration' => $squelette->getConfigurationArray(),
                'nombreUtilisations' => $squelette->getNombreUtilisations(),
            ]
        ]);
    }

    /**
     * API: Create new template
     */
    #[Route('/api', name: 'api_squelette_garde_create', methods: ['POST'])]
    public function apiCreate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $squelette = $this->manager->createSquelette(
                nom: $data['nom'],
                createdBy: $this->getUser(),
                description: $data['description'] ?? null,
                configuration: $data['configuration'] ?? []
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Template créé avec succès.',
                'data' => ['id' => $squelette->getId()]
            ], 201);

        } catch (SqueletteGardeValidationException $e) {
            return new JsonResponse([
                'success' => false,
                'errors' => $e->getErrors(),
                'warnings' => $e->getWarnings(),
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Update template
     */
    #[Route('/api/{id}', name: 'api_squelette_garde_update', methods: ['PUT'])]
    public function apiUpdate(Request $request, SqueletteGarde $squelette): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $this->manager->updateSquelette(
                squelette: $squelette,
                nom: $data['nom'],
                description: $data['description'] ?? null,
                configuration: $data['configuration'],
                updatedBy: $this->getUser()
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Template mis à jour avec succès.'
            ]);

        } catch (SqueletteGardeValidationException $e) {
            return new JsonResponse([
                'success' => false,
                'errors' => $e->getErrors(),
                'warnings' => $e->getWarnings(),
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Validate configuration
     */
    #[Route('/api/validate', name: 'api_squelette_garde_validate', methods: ['POST'])]
    public function apiValidate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $warnings = $this->validator->validateConfiguration($data['configuration'] ?? []);

            return new JsonResponse([
                'success' => true,
                'valid' => true,
                'warnings' => $warnings,
            ]);

        } catch (SqueletteGardeValidationException $e) {
            return new JsonResponse([
                'success' => true,
                'valid' => false,
                'errors' => $e->getErrors(),
                'warnings' => $e->getWarnings(),
            ]);
        }
    }
}
