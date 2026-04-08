<?php

namespace App\Controller\chat;

use App\Entity\Chat\Conversation;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\ConversationRepository;
use App\Service\Chat\ConversationSidebarProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use InvalidArgumentException;

class ConversationController extends AbstractController
{
    public const SESSION_CURRENT_USER_ID = 44;

    #[Route('/apps-chat', name: 'apps-chat')]
    public function index(ConversationSidebarProvider $sidebarProvider): Response
    {
        $sidebarData = $sidebarProvider->getSidebarData(self::SESSION_CURRENT_USER_ID);

        return $this->render('chat/apps-chat.html.twig', [
            'currentUser' => $sidebarData['currentUser'],
            'conversations' => $sidebarData['conversations'],
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/name', name: 'apps-chat-conversation-rename', methods: ['POST'])]
    public function renameConversation(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        $title = trim((string) $request->request->get('title', ''));
        if ($title === '') {
            return $this->json([
                'success' => false,
                'error' => 'Chat name cannot be empty.',
            ], 422);
        }

        try {
            $conversation->setTitle($title);
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'conversation' => $this->formatConversationUpdate($conversation),
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/avatar', name: 'apps-chat-conversation-avatar', methods: ['POST'])]
    public function updateConversationAvatar(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        $avatar = $request->files->get('avatar');
        if (!$avatar instanceof UploadedFile) {
            return $this->json([
                'success' => false,
                'error' => 'Please choose an image file.',
            ], 422);
        }

        $mimeType = (string) ($avatar->getMimeType() ?? '');
        if ($mimeType === '' || !str_starts_with($mimeType, 'image/')) {
            return $this->json([
                'success' => false,
                'error' => 'Only image files are allowed.',
            ], 422);
        }

        $binary = file_get_contents($avatar->getPathname());
        if ($binary === false || $binary === '') {
            return $this->json([
                'success' => false,
                'error' => 'Could not read the selected image.',
            ], 422);
        }

        $conversation->setAvatar($binary);
        $conversation->setAvatarMime($mimeType);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'conversation' => $this->formatConversationUpdate($conversation),
        ]);
    }

    private function loadEditableGroupConversation(
        int $conversationId,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
    ): Conversation|JsonResponse {
        if ($conversationId <= 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid conversation id.',
            ], 400);
        }

        $conversation = $conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            return $this->json([
                'success' => false,
                'error' => 'Conversation not found.',
            ], 404);
        }

        if (!$participantRepository->isActiveParticipant($conversationId, self::SESSION_CURRENT_USER_ID)) {
            return $this->json([
                'success' => false,
                'error' => 'Access denied for this conversation.',
            ], 403);
        }

        if ($this->isDirectConversation($conversation)) {
            return $this->json([
                'success' => false,
                'error' => 'Only group chats can be customized.',
            ], 422);
        }

        if ((int) $conversation->getCreatedBy() !== self::SESSION_CURRENT_USER_ID) {
            return $this->json([
                'success' => false,
                'error' => 'Only the group creator can customize this chat.',
            ], 403);
        }

        return $conversation;
    }

    private function isDirectConversation(Conversation $conversation): bool
    {
        $type = $conversation->getType();
        if ($type !== null && strcasecmp($type, 'dm') === 0) {
            return true;
        }

        return $conversation->getDmKey() !== null;
    }

    private function formatConversationUpdate(Conversation $conversation): array
    {
        return [
            'id' => $conversation->getId(),
            'name' => $conversation->getTitle() ?? 'Untitled Group',
            'avatarSrc' => $this->toDataUri($conversation->getAvatar(), $conversation->getAvatarMime() ?? 'image/jpeg'),
        ];
    }

    private function toDataUri(mixed $blobValue, string $mime): ?string
    {
        if ($blobValue === null || $blobValue === '') {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode((string) $blobValue));
    }
}
