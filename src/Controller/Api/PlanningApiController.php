<?php

namespace App\Controller\Api;

use App\Entity\PlanningMonth;
use App\Entity\Villa;
use App\Repository\AffectationRepository;
use App\Repository\PlanningMonthRepository;
use App\Repository\RendezVousRepository;
use App\Repository\VillaRepository;
use App\Service\Planning\PlanningGeneratorService;
use App\Service\Planning\VillaPlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/planning')]
#[IsGranted('ROLE_USER')]
class PlanningApiController extends AbstractController
{
    public function __construct(
        private PlanningMonthRepository $planningRepository,
        private VillaRepository $villaRepository,
        private AffectationRepository $affectationRepository,
        private RendezVousRepository $rdvRepository,
        private PlanningGeneratorService $generatorService,
        private VillaPlanningService $planningService
    ) {
    }

    #[Route('/villas/{villaId}', methods: ['GET'])]
    public function getPlanning(int $villaId, Request $request): JsonResponse
    {
        $villa = $this->villaRepository->find($villaId);
        if (!$villa) {
            return $this->json(['error' => 'Villa non trouvée'], 404);
        }

        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('m'));

        $planning = $this->planningRepository->findOneBy([
            'villa' => $villa,
            'annee' => $year,
            'mois' => $month,
        ]);

        $affectations = [];
        if ($planning) {
            foreach ($planning->getAffectations() as $aff) {
                $affectations[] = [
                    'id' => $aff->getId(),
                    'start' => $aff->getStartAt()->format('Y-m-d H:i:s'),
                    'end' => $aff->getEndAt()->format('Y-m-d H:i:s'),
                    'type' => $aff->getType(),
                    'status' => $aff->getStatut(),
                    'isFromSquelette' => $aff->isIsFromSquelette(),
                    'user' => $aff->getUser() ? [
                        'id' => $aff->getUser()->getId(),
                        'fullName' => $aff->getUser()->getNom() . ' ' . $aff->getUser()->getPrenom(), // Assuming these fields exist
                    ] : null,
                ];
            }
        }

        // Fetch RDVs for the period (optional, depending on frontend needs)
        // For now, we return the planning structure
        
        return $this->json([
            'planning' => $planning ? [
                'id' => $planning->getId(),
                'status' => $planning->getStatut(),
                'validatedAt' => $planning->getDateValidation()?->format('Y-m-d H:i:s'),
            ] : null,
            'affectations' => $affectations,
        ]);
    }

    #[Route('/villas/{villaId}/generate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function generate(int $villaId, Request $request): JsonResponse
    {
        $villa = $this->villaRepository->find($villaId);
        if (!$villa) {
            return $this->json(['error' => 'Villa non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $year = $data['year'] ?? (int) date('Y');
        $month = $data['month'] ?? (int) date('m');

        try {
            $planning = $this->generatorService->generateSkeleton($villa, $year, $month);
            return $this->json(['message' => 'Planning généré', 'id' => $planning->getId()]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/validate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validate(int $id): JsonResponse
    {
        $planning = $this->planningRepository->find($id);
        if (!$planning) {
            return $this->json(['error' => 'Planning non trouvé'], 404);
        }

        $this->planningService->validatePlanning($planning, $this->getUser());

        return $this->json(['message' => 'Planning validé']);
    }
}
