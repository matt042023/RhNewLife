<?php

namespace App\Controller\Admin;

use App\Entity\MessageInterne;
use App\Entity\User;
use App\Form\MessageInterneType;
use App\Repository\MessageInterneRepository;
use App\Repository\UserRepository;
use App\Service\Communication\MessageInterneService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/messages')]
#[IsGranted('ROLE_ADMIN')]
class MessageInterneController extends AbstractController
{
    public function __construct(
        private MessageInterneRepository $repository,
        private MessageInterneService $service,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Inbox / Sent messages
     */
    #[Route('', name: 'admin_message_interne_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $tab = $request->query->get('tab', 'received');

        $messages = match ($tab) {
            'sent' => $this->repository->findSentByUser($user, $page),
            default => $this->repository->findReceivedByUser($user, $page),
        };

        $receivedCount = $this->repository->countReceivedByUser($user);
        $sentCount = $this->repository->countSentByUser($user);
        $unreadCount = $this->repository->countUnreadForUser($user);

        return $this->render('admin/message_interne/index.html.twig', [
            'messages' => $messages,
            'tab' => $tab,
            'page' => $page,
            'receivedCount' => $receivedCount,
            'sentCount' => $sentCount,
            'unreadCount' => $unreadCount,
            'currentUser' => $user,
        ]);
    }

    /**
     * Compose new message
     */
    #[Route('/compose', name: 'admin_message_interne_compose', methods: ['GET', 'POST'])]
    public function compose(#[CurrentUser] User $user, Request $request): Response
    {
        $form = $this->createForm(MessageInterneType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $destinataires = $data['destinataires'] ?? [];
            $rolesCible = $data['rolesCible'] ?? [];

            if (!empty($rolesCible)) {
                // Send to roles (broadcast)
                $this->service->sendToRoles(
                    $user,
                    $rolesCible,
                    $data['sujet'],
                    $data['contenu'],
                    null // TODO: Handle file uploads
                );
                $this->addFlash('success', 'Message envoye a tous les utilisateurs des roles selectionnes');
            } elseif (!empty($destinataires)) {
                // Send to specific users - convert Collection to array
                $destinatairesArray = is_array($destinataires) ? $destinataires : $destinataires->toArray();
                $this->service->sendToUsers(
                    $user,
                    $destinatairesArray,
                    $data['sujet'],
                    $data['contenu'],
                    null
                );
                $this->addFlash('success', 'Message envoye avec succes');
            } else {
                $this->addFlash('error', 'Veuillez selectionner au moins un destinataire');
                return $this->render('admin/message_interne/compose.html.twig', [
                    'form' => $form,
                    'users' => $this->userRepository->findAllActive(),
                ]);
            }

            return $this->redirectToRoute('admin_message_interne_index', ['tab' => 'sent']);
        }

        return $this->render('admin/message_interne/compose.html.twig', [
            'form' => $form,
            'users' => $this->userRepository->findAllActive(),
        ]);
    }

    /**
     * View message
     */
    #[Route('/{id}', name: 'admin_message_interne_show', methods: ['GET'])]
    public function show(MessageInterne $message, #[CurrentUser] User $user): Response
    {
        // Check access
        if (!$this->service->canAccessMessage($message, $user)) {
            $this->addFlash('error', 'Vous n\'avez pas acces a ce message');
            return $this->redirectToRoute('admin_message_interne_index');
        }

        // Mark as read if recipient
        if ($message->getDestinataires()->contains($user)) {
            $this->service->markAsRead($message, $user);
        }

        return $this->render('admin/message_interne/show.html.twig', [
            'message' => $message,
            'currentUser' => $user,
        ]);
    }

    /**
     * Reply to message
     */
    #[Route('/{id}/reply', name: 'admin_message_interne_reply', methods: ['GET', 'POST'])]
    public function reply(MessageInterne $originalMessage, #[CurrentUser] User $user, Request $request): Response
    {
        // Check access
        if (!$this->service->canAccessMessage($originalMessage, $user)) {
            $this->addFlash('error', 'Vous n\'avez pas acces a ce message');
            return $this->redirectToRoute('admin_message_interne_index');
        }

        $defaultData = [
            'sujet' => 'Re: ' . $originalMessage->getSujet(),
            'destinataires' => [$originalMessage->getExpediteur()],
        ];

        $form = $this->createForm(MessageInterneType::class, $defaultData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $this->service->sendToUsers(
                $user,
                [$originalMessage->getExpediteur()],
                $data['sujet'],
                $data['contenu'],
                null
            );

            $this->addFlash('success', 'Reponse envoyee');
            return $this->redirectToRoute('admin_message_interne_index');
        }

        return $this->render('admin/message_interne/reply.html.twig', [
            'form' => $form,
            'originalMessage' => $originalMessage,
        ]);
    }
}
