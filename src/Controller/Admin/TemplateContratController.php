<?php

namespace App\Controller\Admin;

use App\Entity\TemplateContrat;
use App\Repository\TemplateContratRepository;
use App\Service\TemplateContratManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contract-templates')]
#[IsGranted('ROLE_ADMIN')]
class TemplateContratController extends AbstractController
{
    public function __construct(
        private TemplateContratManager $templateManager,
        private TemplateContratRepository $templateRepository
    ) {}

    /**
     * Liste des templates de contrat
     */
    #[Route('', name: 'app_admin_contract_templates_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $search = $request->query->get('search', '');

        if ($search) {
            $templates = $this->templateRepository->searchByName($search);
        } else {
            $templates = $this->templateRepository->findAllWithContractCount();
        }

        return $this->render('admin/contract_templates/list.html.twig', [
            'templates' => $templates,
            'search' => $search,
        ]);
    }

    /**
     * Formulaire de création d'un template
     */
    #[Route('/create', name: 'app_admin_contract_templates_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'name' => $request->request->get('name'),
                    'description' => $request->request->get('description'),
                    'contentHtml' => $request->request->get('content_html'),
                    'active' => $request->request->get('active', false),
                ];

                $template = $this->templateManager->createTemplate($data);

                $this->addFlash('success', 'Template créé avec succès.');
                return $this->redirectToRoute('app_admin_contract_templates_view', ['id' => $template->getId()]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }

            return $this->render('admin/contract_templates/form.html.twig', [
                'template' => null,
                'errors' => $errors,
                'formData' => $request->request->all(),
                'isEdit' => false,
                'availableVariables' => TemplateContrat::getAvailableVariables(),
                'exampleTemplate' => $this->templateManager->generateExampleTemplate(),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/contract_templates/form.html.twig', [
            'template' => null,
            'errors' => [],
            'formData' => [],
            'isEdit' => false,
            'availableVariables' => TemplateContrat::getAvailableVariables(),
            'exampleTemplate' => $this->templateManager->generateExampleTemplate(),
        ]);
    }

    /**
     * Voir un template
     */
    #[Route('/{id}', name: 'app_admin_contract_templates_view', methods: ['GET'])]
    public function view(TemplateContrat $template): Response
    {
        $stats = $this->templateManager->getTemplateStats($template);

        return $this->render('admin/contract_templates/view.html.twig', [
            'template' => $template,
            'stats' => $stats,
        ]);
    }

    /**
     * Formulaire d'édition d'un template
     */
    #[Route('/{id}/edit', name: 'app_admin_contract_templates_edit', methods: ['GET', 'POST'])]
    public function edit(TemplateContrat $template, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];

            try {
                $data = [
                    'name' => $request->request->get('name'),
                    'description' => $request->request->get('description'),
                    'contentHtml' => $request->request->get('content_html'),
                    'active' => $request->request->get('active', false),
                ];

                $this->templateManager->updateTemplate($template, $data);

                $this->addFlash('success', 'Template mis à jour avec succès.');
                return $this->redirectToRoute('app_admin_contract_templates_view', ['id' => $template->getId()]);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }

            return $this->render('admin/contract_templates/form.html.twig', [
                'template' => $template,
                'errors' => $errors,
                'formData' => $request->request->all(),
                'isEdit' => true,
                'availableVariables' => TemplateContrat::getAvailableVariables(),
                'exampleTemplate' => $this->templateManager->generateExampleTemplate(),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('admin/contract_templates/form.html.twig', [
            'template' => $template,
            'errors' => [],
            'formData' => [],
            'isEdit' => true,
            'availableVariables' => TemplateContrat::getAvailableVariables(),
            'exampleTemplate' => $this->templateManager->generateExampleTemplate(),
        ]);
    }

    /**
     * Désactiver un template
     */
    #[Route('/{id}/deactivate', name: 'app_admin_contract_templates_deactivate', methods: ['POST'])]
    public function deactivate(TemplateContrat $template): Response
    {
        try {
            $this->templateManager->deactivateTemplate($template);
            $this->addFlash('success', 'Template désactivé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contract_templates_view', ['id' => $template->getId()]);
    }

    /**
     * Activer un template
     */
    #[Route('/{id}/activate', name: 'app_admin_contract_templates_activate', methods: ['POST'])]
    public function activate(TemplateContrat $template): Response
    {
        try {
            $this->templateManager->activateTemplate($template);
            $this->addFlash('success', 'Template activé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contract_templates_view', ['id' => $template->getId()]);
    }

    /**
     * Prévisualisation du template (HTML)
     */
    #[Route('/{id}/preview', name: 'app_admin_contract_templates_preview', methods: ['GET'])]
    public function preview(TemplateContrat $template): Response
    {
        return $this->render('admin/contract_templates/preview.html.twig', [
            'template' => $template,
        ]);
    }
}
