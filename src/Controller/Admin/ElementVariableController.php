<?php

namespace App\Controller\Admin;

use App\Entity\ConsolidationPaie;
use App\Entity\ElementVariable;
use App\Entity\User;
use App\Repository\ConsolidationPaieRepository;
use App\Repository\ElementVariableRepository;
use App\Repository\UserRepository;
use App\Service\Payroll\ElementVariableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/variables')]
#[IsGranted('ROLE_ADMIN')]
class ElementVariableController extends AbstractController
{
    public function __construct(
        private ElementVariableRepository $elementVariableRepository,
        private ConsolidationPaieRepository $consolidationRepository,
        private UserRepository $userRepository,
        private ElementVariableService $elementVariableService
    ) {
    }

    /**
     * Liste des éléments variables (filtrable par période et utilisateur)
     */
    #[Route('', name: 'admin_element_variable_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $period = $request->query->get('period', date('Y-m'));
        $userId = $request->query->get('user');

        $criteria = ['period' => $period];
        if ($userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $criteria['user'] = $user;
            }
        }

        $elements = $this->elementVariableRepository->findBy(
            $criteria,
            ['user' => 'ASC', 'category' => 'ASC', 'createdAt' => 'ASC']
        );

        $users = $this->userRepository->findActiveEducators();
        $categories = $this->elementVariableService->getCategories();

        return $this->render('admin/element_variable/index.html.twig', [
            'elements' => $elements,
            'period' => $period,
            'selectedUserId' => $userId,
            'users' => $users,
            'categories' => $categories,
        ]);
    }

    /**
     * Formulaire de création
     */
    #[Route('/nouveau', name: 'admin_element_variable_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $users = $this->userRepository->findActiveEducators();
        $categories = $this->elementVariableService->getCategories();

        // Pré-remplir depuis la query string
        $period = $request->query->get('period', date('Y-m'));
        $userId = $request->query->get('user');
        $consolidationId = $request->query->get('consolidation');

        if ($request->isMethod('POST')) {
            /** @var User $admin */
            $admin = $this->getUser();

            $userId = $request->request->get('user');
            $user = $this->userRepository->find($userId);

            if (!$user) {
                $this->addFlash('error', 'Utilisateur non trouvé.');
                return $this->redirectToRoute('admin_element_variable_new');
            }

            $element = $this->elementVariableService->create(
                $user,
                $request->request->get('period'),
                $request->request->get('category'),
                $request->request->get('label'),
                $request->request->get('amount'),
                $request->request->get('description'),
                $admin
            );

            $this->addFlash('success', 'Élément variable créé avec succès.');

            // Rediriger vers la consolidation si on venait de là
            if ($consolidationId) {
                return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidationId]);
            }

            return $this->redirectToRoute('admin_element_variable_index', ['period' => $element->getPeriod()]);
        }

        return $this->render('admin/element_variable/new.html.twig', [
            'users' => $users,
            'categories' => $categories,
            'period' => $period,
            'selectedUserId' => $userId,
            'consolidationId' => $consolidationId,
        ]);
    }

    /**
     * Formulaire d'édition
     */
    #[Route('/{id}/modifier', name: 'admin_element_variable_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ElementVariable $element): Response
    {
        $categories = $this->elementVariableService->getCategories();

        if ($request->isMethod('POST')) {
            /** @var User $admin */
            $admin = $this->getUser();

            $this->elementVariableService->update(
                $element,
                $request->request->get('category'),
                $request->request->get('label'),
                $request->request->get('amount'),
                $request->request->get('description'),
                $admin
            );

            $this->addFlash('success', 'Élément variable modifié avec succès.');

            $consolidation = $element->getConsolidation();
            if ($consolidation) {
                return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidation->getId()]);
            }

            return $this->redirectToRoute('admin_element_variable_index', ['period' => $element->getPeriod()]);
        }

        return $this->render('admin/element_variable/edit.html.twig', [
            'element' => $element,
            'categories' => $categories,
        ]);
    }

    /**
     * Suppression
     */
    #[Route('/{id}/supprimer', name: 'admin_element_variable_delete', methods: ['POST'])]
    public function delete(Request $request, ElementVariable $element): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $element->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_element_variable_index');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $period = $element->getPeriod();
        $consolidation = $element->getConsolidation();

        $this->elementVariableService->delete($element, $admin);

        $this->addFlash('success', 'Élément variable supprimé.');

        if ($consolidation) {
            return $this->redirectToRoute('admin_payroll_show', ['id' => $consolidation->getId()]);
        }

        return $this->redirectToRoute('admin_element_variable_index', ['period' => $period]);
    }

    /**
     * Créer via AJAX (depuis la page de consolidation)
     */
    #[Route('/ajax/creer', name: 'admin_element_variable_ajax_create', methods: ['POST'])]
    public function ajaxCreate(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $userId = $data['user_id'] ?? null;
        $user = $userId ? $this->userRepository->find($userId) : null;

        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 400);
        }

        try {
            $element = $this->elementVariableService->create(
                $user,
                $data['period'] ?? date('Y-m'),
                $data['category'] ?? ElementVariable::CATEGORY_PRIME,
                $data['label'] ?? '',
                $data['amount'] ?? '0',
                $data['description'] ?? null,
                $admin
            );

            return new JsonResponse([
                'success' => true,
                'element' => [
                    'id' => $element->getId(),
                    'category' => $element->getCategory(),
                    'category_label' => $element->getCategoryLabel(),
                    'label' => $element->getLabel(),
                    'amount' => (float) $element->getAmount(),
                    'description' => $element->getDescription(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Mettre à jour via AJAX
     */
    #[Route('/ajax/{id}/modifier', name: 'admin_element_variable_ajax_update', methods: ['POST'])]
    public function ajaxUpdate(Request $request, ElementVariable $element): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $data = json_decode($request->getContent(), true);

        try {
            $this->elementVariableService->update(
                $element,
                $data['category'] ?? $element->getCategory(),
                $data['label'] ?? $element->getLabel(),
                $data['amount'] ?? $element->getAmount(),
                $data['description'] ?? $element->getDescription(),
                $admin
            );

            return new JsonResponse([
                'success' => true,
                'element' => [
                    'id' => $element->getId(),
                    'category' => $element->getCategory(),
                    'category_label' => $element->getCategoryLabel(),
                    'label' => $element->getLabel(),
                    'amount' => (float) $element->getAmount(),
                    'description' => $element->getDescription(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Supprimer via AJAX
     */
    #[Route('/ajax/{id}/supprimer', name: 'admin_element_variable_ajax_delete', methods: ['DELETE'])]
    public function ajaxDelete(ElementVariable $element): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        try {
            $this->elementVariableService->delete($element, $admin);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Retourne les catégories disponibles (API)
     */
    #[Route('/api/categories', name: 'admin_element_variable_categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        return new JsonResponse($this->elementVariableService->getCategories());
    }
}
