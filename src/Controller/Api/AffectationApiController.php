<?php

namespace App\Controller\Api;

use App\Entity\Affectation;
use App\Entity\User;
use App\Repository\AffectationRepository;
use App\Repository\PlanningMonthRepository;
use App\Repository\VillaRepository;
use App\Service\Planning\VillaPlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/affectations')]
#[IsGranted('ROLE_USER')]
class AffectationApiController extends AbstractController
{
    public function __construct(
        private AffectationRepository $affectationRepository,
        private PlanningMonthRepository $planningRepository,
        private VillaRepository $villaRepository,
        private VillaPlanningService $planningService
    ) {
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $planning = $this->planningRepository->find($data['planningId'] ?? 0);
        $villa = $this->villaRepository->find($data['villaId'] ?? 0);
        
        if (!$planning || !$villa) {
            return $this->json(['error' => 'Planning ou Villa invalide'], 400);
        }

        $start = new \DateTime($data['startAt']);
        $end = new \DateTime($data['endAt']);
        $type = $data['type'] ?? Affectation::TYPE_AUTRE;
        
        $user = null;
        if (!empty($data['userId'])) {
            $user = $this->getDoctrine()->getRepository(User::class)->find($data['userId']);
        }

        try {
            $affectation = $this->planningService->createManualAffectation($planning, $villa, $start, $end, $type, $user);
            return $this->json(['id' => $affectation->getId()], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $affectation = $this->affectationRepository->find($id);
        if (!$affectation) {
            return $this->json(['error' => 'Affectation non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['userId'])) {
            $user = $data['userId'] ? $this->getDoctrine()->getRepository(User::class)->find($data['userId']) : null;
            $this->planningService->assignUser($affectation, $user);
        }

        if (isset($data['startAt'])) {
            $affectation->setStartAt(new \DateTime($data['startAt']));
        }
        if (isset($data['endAt'])) {
            $affectation->setEndAt(new \DateTime($data['endAt']));
        }
        if (isset($data['type'])) {
            $affectation->setType($data['type']);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json(['message' => 'Affectation mise à jour']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $affectation = $this->affectationRepository->find($id);
        if (!$affectation) {
            return $this->json(['error' => 'Affectation non trouvée'], 404);
        }

        $this->getDoctrine()->getManager()->remove($affectation);
        $this->getDoctrine()->getManager()->flush();

        return $this->json(['message' => 'Affectation supprimée']);
    }
}
