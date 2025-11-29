<?php

namespace App\Controller\Api;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\RendezVousRepository;
use App\Service\Planning\RendezVousService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/rendezvous')]
#[IsGranted('ROLE_USER')]
class RendezVousApiController extends AbstractController
{
    public function __construct(
        private RendezVousRepository $rdvRepository,
        private RendezVousService $rdvService
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $start = new \DateTime($request->query->get('from', 'now'));
        $end = new \DateTime($request->query->get('to', '+1 month'));

        $qb = $this->rdvRepository->createQueryBuilder('r')
            ->where('r.startAt <= :end')
            ->andWhere('r.endAt >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        // Filter by participant if needed
        // ...

        $rdvs = $qb->getQuery()->getResult();

        $data = [];
        foreach ($rdvs as $rdv) {
            $data[] = [
                'id' => $rdv->getId(),
                'title' => $rdv->getTitre(),
                'start' => $rdv->getStartAt()->format('Y-m-d H:i:s'),
                'end' => $rdv->getEndAt()->format('Y-m-d H:i:s'),
                'type' => $rdv->getType(),
                'impactGarde' => $rdv->isImpactGarde(),
                'participants' => array_map(fn(User $u) => $u->getId(), $rdv->getParticipants()->toArray()),
            ];
        }

        return $this->json($data);
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')] // Or specific role
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $rdv = new RendezVous();
        $rdv->setTitre($data['title']);
        $rdv->setDescription($data['description'] ?? null);
        $rdv->setStartAt(new \DateTime($data['startAt']));
        $rdv->setEndAt(new \DateTime($data['endAt']));
        $rdv->setType($data['type'] ?? RendezVous::TYPE_INDIVIDUEL);
        $rdv->setImpactGarde($data['impactGarde'] ?? false);
        $rdv->setCreatedBy($this->getUser());

        if (!empty($data['participants'])) {
            foreach ($data['participants'] as $userId) {
                $user = $this->getDoctrine()->getRepository(User::class)->find($userId);
                if ($user) {
                    $rdv->addParticipant($user);
                }
            }
        }

        $this->rdvService->createRendezVous($rdv);

        return $this->json(['id' => $rdv->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $rdv = $this->rdvRepository->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) $rdv->setTitre($data['title']);
        if (isset($data['startAt'])) $rdv->setStartAt(new \DateTime($data['startAt']));
        if (isset($data['endAt'])) $rdv->setEndAt(new \DateTime($data['endAt']));
        if (isset($data['impactGarde'])) $rdv->setImpactGarde($data['impactGarde']);

        // Handle participants update if needed
        // ...

        $this->rdvService->updateRendezVous($rdv);

        return $this->json(['message' => 'RDV mis à jour']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $rdv = $this->rdvRepository->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV non trouvé'], 404);
        }

        $this->rdvService->deleteRendezVous($rdv);

        return $this->json(['message' => 'RDV supprimé']);
    }
}
