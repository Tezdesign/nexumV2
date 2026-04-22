<?php

namespace App\Controller\chat;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\ConversationParticipant;
use App\Entity\Chat\Message;
use App\Entity\Chat\MessageAttachment;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\ConversationRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\Chat\ConversationSidebarProvider;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use InvalidArgumentException;

class ConversationController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly HttpClientInterface $httpClient,
    )
    {
    }

    #[Route('/apps-chat', name: 'apps-chat')]
    public function index(ConversationSidebarProvider $sidebarProvider): Response
    {
        $sidebarData = $sidebarProvider->getSidebarData($this->currentUserId());
        $currentUserId = $this->currentUserId();
        $gifApiKey = trim((string) (
            $_ENV['KILPY']
            ?? $_ENV['KLIPY_API_KEY']
            ?? $_SERVER['KILPY']
            ?? $_SERVER['KLIPY_API_KEY']
            ?? getenv('KILPY')
            ?? getenv('KLIPY_API_KEY')
            ?? ''
        ));
        $gifApiBaseUrl = rtrim((string) (
            $_ENV['KILPY_BASE_URL']
            ?? $_ENV['KLIPY_BASE_URL']
            ?? $_SERVER['KILPY_BASE_URL']
            ?? $_SERVER['KLIPY_BASE_URL']
            ?? getenv('KILPY_BASE_URL')
            ?? getenv('KLIPY_BASE_URL')
            ?? 'https://api.klipy.com'
        ), '/');
        $gifLocale = strtolower(trim((string) (
            $_ENV['KILPY_LOCALE']
            ?? $_ENV['KLIPY_LOCALE']
            ?? $_SERVER['KILPY_LOCALE']
            ?? $_SERVER['KLIPY_LOCALE']
            ?? getenv('KILPY_LOCALE')
            ?? getenv('KLIPY_LOCALE')
            ?? 'tn'
        )));
        $callSocketUrl = trim((string) (
            $_ENV['CHAT_CALL_SOCKET_URL']
            ?? $_SERVER['CHAT_CALL_SOCKET_URL']
            ?? getenv('CHAT_CALL_SOCKET_URL')
            ?? 'ws://localhost:8090/ws'
        ));
        $callTokenEndpoint = trim((string) (
            $_ENV['CHAT_CALL_TOKEN_ENDPOINT']
            ?? $_SERVER['CHAT_CALL_TOKEN_ENDPOINT']
            ?? getenv('CHAT_CALL_TOKEN_ENDPOINT')
            ?? '/apps-chat/livekit/token'
        ));
        $callPageEndpoint = trim((string) (
            $_ENV['CHAT_CALL_PAGE_ENDPOINT']
            ?? $_SERVER['CHAT_CALL_PAGE_ENDPOINT']
            ?? getenv('CHAT_CALL_PAGE_ENDPOINT')
            ?? 'http://127.0.0.1:8090/livekit/call'
        ));
        $callLivekitUrl = trim((string) (
            $_ENV['CHAT_CALL_LIVEKIT_URL']
            ?? $_SERVER['CHAT_CALL_LIVEKIT_URL']
            ?? getenv('CHAT_CALL_LIVEKIT_URL')
            ?? 'ws://127.0.0.1:7880'
        ));
        $callAvatarEndpoint = trim((string) (
            $_ENV['CHAT_CALL_AVATAR_ENDPOINT']
            ?? $_SERVER['CHAT_CALL_AVATAR_ENDPOINT']
            ?? getenv('CHAT_CALL_AVATAR_ENDPOINT')
            ?? '/apps-chat/livekit/avatar'
        ));
        $callSignalingEnabled = filter_var(
            (string) (
                $_ENV['CHAT_CALL_SIGNALING_ENABLED']
                ?? $_SERVER['CHAT_CALL_SIGNALING_ENABLED']
                ?? getenv('CHAT_CALL_SIGNALING_ENABLED')
                ?? '1'
            ),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );
        if ($callSignalingEnabled === null) {
            $callSignalingEnabled = true;
        }

        return $this->render('chat/apps-chat.html.twig', [
            'currentUser' => $sidebarData['currentUser'],
            'conversations' => $sidebarData['conversations'],
            'gifApiKey' => $gifApiKey,
            'gifApiProvider' => 'kilpy',
            'gifApiBaseUrl' => $gifApiBaseUrl,
            'gifCustomerId' => (string) $currentUserId,
            'gifLocale' => $gifLocale,
            'callSignalingEnabled' => $callSignalingEnabled,
            'callSocketUrl' => $callSocketUrl,
            'callTokenEndpoint' => $callTokenEndpoint,
            'callPageEndpoint' => $callPageEndpoint,
            'callLivekitUrl' => $callLivekitUrl,
            'callAvatarEndpoint' => $callAvatarEndpoint,
        ]);
    }

    #[Route('/apps-chat/livekit/token', name: 'apps-chat-livekit-token-proxy', methods: ['GET'])]
    public function proxyLivekitToken(Request $request): Response
    {
        $target = rtrim($this->readEnvSetting([
            'CHAT_CALL_TOKEN_PROXY_TARGET',
            'CHAT_CALL_TOKEN_ENDPOINT',
        ], 'http://127.0.0.1:8090/livekit/token'), '/');

        try {
            $upstream = $this->httpClient->request('GET', $target, [
                'query' => $request->query->all(),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $status = $upstream->getStatusCode();
            $content = $upstream->getContent(false);
            $headers = $upstream->getHeaders(false);

            return new Response($content, $status, [
                'Content-Type' => $headers['content-type'][0] ?? 'application/json',
            ]);
        } catch (\Throwable $error) {
            return $this->json([
                'success' => false,
                'error' => 'LiveKit token proxy failed.',
                'detail' => $error->getMessage(),
            ], 502);
        }
    }

    #[Route('/apps-chat/livekit/avatar/{userId}', name: 'apps-chat-livekit-avatar-proxy', methods: ['GET'])]
    public function proxyLivekitAvatar(int $userId): Response
    {
        $base = rtrim($this->readEnvSetting([
            'CHAT_CALL_AVATAR_PROXY_TARGET',
            'CHAT_CALL_AVATAR_ENDPOINT',
        ], 'http://127.0.0.1:8090/livekit/avatar'), '/');

        $target = sprintf('%s/%d', $base, $userId);

        try {
            $upstream = $this->httpClient->request('GET', $target);
            $status = $upstream->getStatusCode();
            $content = $upstream->getContent(false);
            $headers = $upstream->getHeaders(false);

            return new Response($content, $status, [
                'Content-Type' => $headers['content-type'][0] ?? 'application/octet-stream',
                'Cache-Control' => 'public, max-age=300',
            ]);
        } catch (\Throwable $error) {
            return new Response('', 404);
        }
    }

    private function readEnvSetting(array $keys, string $fallback = ''): string
    {
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($value === false) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return trim($fallback);
    }

    /**
     * Send call invite message
     */
    #[Route('/apps-chat/conversations/{conversationId}/call/invite', name: 'apps-chat-call-invite', methods: ['POST'])]
    public function sendCallInvite(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        MessageRepository $messageRepository,
        ConversationParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $videoEnabled = $request->request->getBoolean('video', false);
        $currentUserId = $this->currentUserId();

        try {
            // Verify user is participant
            if (!$participantRepository->isActiveParticipant($conversationId, $currentUserId)) {
                return $this->json(['success' => false, 'error' => 'Access denied'], 403);
            }

            $conversation = $conversationRepository->find($conversationId);
            if (!$conversation) {
                return $this->json(['success' => false, 'error' => 'Conversation not found'], 404);
            }

            $room = 'conv_' . $conversationId;

            // Create CALL message
            $message = new Message();
            $message->setConversationId($conversationId);
            $message->setSenderId($currentUserId);
            $message->setKind('CALL');
            $message->setBody(($videoEnabled ? 'VIDEO|' : 'AUDIO|') . $room);
            $message->setCreatedAt(new DateTimeImmutable());

            $entityManager->persist($message);
            $entityManager->flush();

            // Send STOMP signal if enabled
            if ($this->isCallSignalingEnabled()) {
                $this->sendCallSignal('RING', $conversationId, $currentUserId, $videoEnabled, $room);
            }

            return $this->json([
                'success' => true,
                'messageId' => $message->getId(),
                'room' => $room,
                'video' => $videoEnabled
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to send call invite: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept call
     */
    #[Route('/apps-chat/conversations/{conversationId}/call/accept', name: 'apps-chat-call-accept', methods: ['POST'])]
    public function acceptCall(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository
    ): JsonResponse {
        $videoEnabled = $request->request->getBoolean('video', false);
        $currentUserId = $this->currentUserId();

        try {
            // Verify user is participant
            if (!$participantRepository->isActiveParticipant($conversationId, $currentUserId)) {
                return $this->json(['success' => false, 'error' => 'Access denied'], 403);
            }

            $room = 'conv_' . $conversationId;

            // Send STOMP signal
            if ($this->isCallSignalingEnabled()) {
                $this->sendCallSignal('ACCEPT', $conversationId, $currentUserId, $videoEnabled, $room);
            }

            return $this->json([
                'success' => true,
                'room' => $room,
                'video' => $videoEnabled,
                'callUrl' => $this->buildCallUrl($videoEnabled, $room)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to accept call: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject call
     */
    #[Route('/apps-chat/conversations/{conversationId}/call/reject', name: 'apps-chat-call-reject', methods: ['POST'])]
    public function rejectCall(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository
    ): JsonResponse {
        $videoEnabled = $request->request->getBoolean('video', false);
        $currentUserId = $this->currentUserId();

        try {
            // Verify user is participant
            if (!$participantRepository->isActiveParticipant($conversationId, $currentUserId)) {
                return $this->json(['success' => false, 'error' => 'Access denied'], 403);
            }

            $room = 'conv_' . $conversationId;

            // Send STOMP signal
            if ($this->isCallSignalingEnabled()) {
                $this->sendCallSignal('REJECT', $conversationId, $currentUserId, $videoEnabled, $room);
            }

            return $this->json([
                'success' => true,
                'message' => 'Call rejected'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to reject call: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call messages for polling
     */
    #[Route('/apps-chat/calls/poll', name: 'apps-chat-calls-poll', methods: ['GET'])]
    public function pollCallMessages(
        Request $request,
        ConversationRepository $conversationRepository,
        MessageRepository $messageRepository,
        ConversationParticipantRepository $participantRepository
    ): JsonResponse {
        $lastMessageId = $request->query->getInt('lastMessageId', 0);
        $currentUserId = $this->currentUserId();

        try {
            // For now, return empty result to avoid 500 error
            // TODO: Fix the repository method after debugging
            return $this->json([
                'success' => true,
                'callMessages' => [],
                'lastMessageId' => $lastMessageId
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to poll call messages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get LiveKit token for call
     */
    #[Route('/apps-chat/conversations/{conversationId}/call/token', name: 'apps-chat-call-token', methods: ['GET'])]
    public function getCallToken(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository
    ): JsonResponse {
        $room = $request->query->get('room', 'conv_' . $conversationId);
        $currentUserId = $this->currentUserId();

        try {
            // Verify user is participant
            if (!$participantRepository->isActiveParticipant($conversationId, $currentUserId)) {
                return $this->json(['success' => false, 'error' => 'Access denied'], 403);
            }

            $tokenEndpoint = $this->readEnvSetting([
                'CHAT_CALL_TOKEN_PROXY_TARGET',
                'CHAT_CALL_TOKEN_ENDPOINT',
            ], 'http://127.0.0.1:8090/livekit/token');

            $response = $this->httpClient->request('GET', $tokenEndpoint, [
                'query' => [
                    'room' => $room,
                    'identity' => 'user_' . $currentUserId,
                    'name' => 'User ' . $currentUserId
                ]
            ]);

            $data = $response->toArray();
            
            if (!isset($data['token'])) {
                return $this->json(['success' => false, 'error' => 'Token not found in response'], 500);
            }

            return $this->json([
                'success' => true,
                'token' => $data['token'],
                'room' => $room
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to get call token: ' . $e->getMessage()
            ], 500);
        }
    }

    private function isCallSignalingEnabled(): bool
    {
        return filter_var(
            $this->readEnvSetting(['CHAT_CALL_SIGNALING_ENABLED'], '1'),
            FILTER_VALIDATE_BOOL
        );
    }

    private function sendCallSignal(string $type, int $conversationId, int $fromUserId, bool $video, string $room): void
    {
        // This would send STOMP signal via WebSocket server
        // For now, we'll just log it - in a real implementation, this would
        // connect to the STOMP server and send the signal
        $payload = [
            'type' => $type,
            'conversationId' => $conversationId,
            'fromUserId' => $fromUserId,
            'fromName' => 'User ' . $fromUserId,
            'callKind' => $video ? 'VIDEO' : 'AUDIO',
            'room' => $room
        ];

        // Log for debugging - replace with actual STOMP send
        error_log('STOMP Signal: ' . json_encode($payload));
    }

    private function buildCallUrl(bool $videoEnabled, string $room): string
    {
        $livekitUrl = $this->readEnvSetting(['CHAT_CALL_LIVEKIT_URL'], 'ws://127.0.0.1:7880');
        $callPageEndpoint = $this->readEnvSetting(['CHAT_CALL_PAGE_ENDPOINT'], 'http://127.0.0.1:8090/livekit/call');
        
        // This would normally fetch a token, but for now we'll return the base URL
        return $callPageEndpoint . '?wsUrl=' . urlencode($livekitUrl) . '&room=' . urlencode($room) . '&mic=true&cam=' . ($videoEnabled ? 'true' : 'false');
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
        try {
            $conversation->renameBy($this->currentUserId(), $title);
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

        if (!$avatar->isValid()) {
            return $this->json([
                'success' => false,
                'error' => $this->formatUploadFailureMessage($avatar),
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

        try {
            $conversation->updateAvatarBy($this->currentUserId(), $binary, $mimeType);
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

    #[Route('/apps-chat/conversations/{conversationId}/participants', name: 'apps-chat-conversation-participants', methods: ['GET'])]
    public function listParticipants(
        int $conversationId,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        UtilisateurRepository $utilisateurRepository,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        $participants = $this->findActiveParticipants($participantRepository, $conversationId);
        $usersById = $this->mapUsersByIds($participants, $utilisateurRepository);
        $actorIsAdmin = $this->hasActorAdminPrivileges($conversation, $participants, $this->currentUserId());

        return $this->json([
            'success' => true,
            'members' => array_map(function (ConversationParticipant $participant) use ($conversation, $usersById, $actorIsAdmin): array {
                $userId = (int) ($participant->getUserId() ?? 0);
                $user = $usersById[$userId] ?? null;
                $isSelf = $userId === $this->currentUserId();
                $displayName = $participant->getNickname() ?? $this->buildUserName($user);

                $subtitle = 'Member';
                if ($userId === (int) ($conversation->getCreatedBy() ?? 0)) {
                    $subtitle = 'Group creator';
                } elseif ($participant->getAddedBy() !== null) {
                    $addedByName = $this->buildUserName($usersById[(int) $participant->getAddedBy()] ?? null);
                    $subtitle = 'Added by ' . $addedByName;
                }

                return [
                    'userId' => $userId,
                    'name' => $displayName,
                    'nickname' => $participant->getNickname(),
                    'fullName' => $this->buildUserName($user),
                    'subtitle' => $subtitle,
                    'avatarSrc' => $user !== null ? $this->toDataUri($user->getImagelink(), 'image/jpeg') : null,
                    'isSelf' => $isSelf,
                    'canRenameNickname' => true,
                    'canKick' => $conversation->isGroupConversation() && $actorIsAdmin && !$isSelf,
                ];
            }, $participants),
            'actorIsAdmin' => $actorIsAdmin,
            'canAddMembers' => $conversation->isGroupConversation(),
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/participants/candidates', name: 'apps-chat-conversation-participants-candidates', methods: ['GET'])]
    public function listParticipantCandidates(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        UtilisateurRepository $utilisateurRepository,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        try {
            $conversation->assertGroupConversation();
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        $search = trim((string) $request->query->get('q', ''));
        $activeParticipants = $this->findActiveParticipants($participantRepository, $conversationId);
        $activeUserIds = array_values(array_filter(array_map(
            static fn (ConversationParticipant $participant): ?int => $participant->getUserId(),
            $activeParticipants
        )));

        $qb = $utilisateurRepository->createQueryBuilder('u');
        if ($activeUserIds !== []) {
            $qb->andWhere('u.id NOT IN (:activeUserIds)')
                ->setParameter('activeUserIds', $activeUserIds);
        }

        if ($search !== '') {
            $qb->andWhere('LOWER(u.nom) LIKE :search OR LOWER(u.prenom) LIKE :search OR LOWER(u.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $candidates = $qb
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'candidates' => array_map(function (Utilisateur $user): array {
                return [
                    'userId' => $user->getId(),
                    'name' => $this->buildUserName($user),
                    'role' => $user->getRole() ?? 'Member',
                    'avatarSrc' => $this->toDataUri($user->getImagelink(), 'image/jpeg'),
                ];
            }, $candidates),
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/participants/add', name: 'apps-chat-conversation-participants-add', methods: ['POST'])]
    public function addParticipant(
        int $conversationId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        UtilisateurRepository $utilisateurRepository,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        try {
            $conversation->assertGroupConversation();
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        $userId = (int) $request->request->get('userId', 0);
        if ($userId <= 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid member id.',
            ], 422);
        }

        $user = $utilisateurRepository->find($userId);
        if (!$user instanceof Utilisateur) {
            return $this->json([
                'success' => false,
                'error' => 'User not found.',
            ], 404);
        }

        try {
            $participantRepository->addOrReactivateParticipant($conversationId, $userId, $this->currentUserId());
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'error' => 'Unable to add this member right now. Please try again.',
            ], 500);
        }

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/participants/{userId}/nickname', name: 'apps-chat-conversation-participant-nickname', methods: ['POST'])]
    public function updateParticipantNickname(
        int $conversationId,
        int $userId,
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        $participant = $participantRepository->findOneBy([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'left_at' => null,
        ]);
        if (!$participant instanceof ConversationParticipant) {
            return $this->json([
                'success' => false,
                'error' => 'Member not found in this conversation.',
            ], 404);
        }

        $nickname = (string) $request->request->get('nickname', '');

        try {
            $conversation->assertCanRenameParticipant($this->currentUserId(), $userId);
            $participant->renameTo($nickname);
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/participants/{userId}/kick', name: 'apps-chat-conversation-participant-kick', methods: ['POST'])]
    public function kickParticipant(
        int $conversationId,
        int $userId,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
    ): JsonResponse {
        $conversation = $this->loadEditableGroupConversation($conversationId, $conversationRepository, $participantRepository);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }

        $activeParticipants = $this->findActiveParticipants($participantRepository, $conversationId);
        $actorHasAdminPrivileges = $this->hasActorAdminPrivileges($conversation, $activeParticipants, $this->currentUserId());

        try {
            $conversation->assertCanKickParticipant($this->currentUserId(), $userId, $actorHasAdminPrivileges);
            $participantRepository->removeParticipant($conversationId, $userId);
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route('/apps-chat/conversations/{conversationId}/danger-action', name: 'apps-chat-conversation-danger-action', methods: ['POST'])]
    public function executeDangerAction(
        int $conversationId,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $conversation = $conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            return $this->json([
                'success' => false,
                'error' => 'Conversation not found.',
            ], 404);
        }

        $isParticipant = $participantRepository->isActiveParticipant($conversationId, $this->currentUserId());
        if (!$isParticipant) {
            return $this->json([
                'success' => false,
                'error' => 'Access denied for this conversation.',
            ], 403);
        }

        $isAdmin = (int) ($conversation->getCreatedBy() ?? 0) === $this->currentUserId();
        $shouldDeleteConversation = $conversation->isGroupConversation() ? $isAdmin : true;

        if (!$shouldDeleteConversation) {
            try {
                $participantRepository->removeParticipant($conversationId, $this->currentUserId());
            } catch (InvalidArgumentException $exception) {
                return $this->json([
                    'success' => false,
                    'error' => $exception->getMessage(),
                ], 422);
            }

            return $this->json([
                'success' => true,
                'action' => 'left',
                'conversationId' => $conversationId,
            ]);
        }

        $messages = $entityManager->getRepository(Message::class)->findBy([
            'conversation_id' => $conversationId,
        ]);
        $messageIds = array_values(array_filter(array_map(
            static fn (Message $message): ?int => $message->getId(),
            $messages
        )));

        if ($messageIds !== []) {
            $attachments = $entityManager->getRepository(MessageAttachment::class)->findBy([
                'message_id' => $messageIds,
            ]);

            foreach ($attachments as $attachment) {
                $entityManager->remove($attachment);
            }
        }

        foreach ($messages as $message) {
            $entityManager->remove($message);
        }

        $participants = $entityManager->getRepository(ConversationParticipant::class)->findBy([
            'conversation_id' => $conversationId,
        ]);

        foreach ($participants as $participant) {
            $entityManager->remove($participant);
        }

        $entityManager->remove($conversation);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'action' => 'deleted',
            'conversationId' => $conversationId,
        ]);
    }

    private function currentUserId(): int
    {
        return (int) ($this->authService->getCurrentUserId() ?? 0);
    }

    /**
     * Render call page
     */
    #[Route('/apps-chat/call', name: 'apps-chat-call-page', methods: ['GET'])]
    public function callPage(Request $request): Response
    {
        $wsUrl = $request->query->get('wsUrl', '');
        $token = $request->query->get('token', '');
        $mic = $request->query->getBoolean('mic', true);
        $cam = $request->query->getBoolean('cam', false);

        // Validate required parameters
        if (empty($wsUrl) || empty($token)) {
            return new Response('Missing required parameters: wsUrl or token', 400);
        }

        return $this->render('chat/call.html.twig', [
            'wsUrl' => $wsUrl,
            'token' => $token,
            'mic' => $mic,
            'cam' => $cam
        ]);
    }

    private function formatUploadFailureMessage(UploadedFile $file): string
    {
        $errorCode = $file->getError();
        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            $serverLimit = trim((string) ini_get('upload_max_filesize'));
            $message = 'Upload failed: file is larger than the server upload limit';
            if ($serverLimit !== '') {
                $message .= sprintf(' (upload_max_filesize=%s)', $serverLimit);
            }

            return $message . '.';
        }

        return 'Upload failed: ' . $file->getErrorMessage();
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

        if (!$participantRepository->isActiveParticipant($conversationId, $this->currentUserId())) {
            return $this->json([
                'success' => false,
                'error' => 'Access denied for this conversation.',
            ], 403);
        }

        return $conversation;
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

    /**
     * @return ConversationParticipant[]
     */
    private function findActiveParticipants(ConversationParticipantRepository $participantRepository, int $conversationId): array
    {
        return $participantRepository->findBy([
            'conversation_id' => $conversationId,
            'left_at' => null,
        ], [
            'joined_at' => 'ASC',
        ]);
    }

    /**
     * @param ConversationParticipant[] $participants
     *
     * @return array<int, Utilisateur>
     */
    private function mapUsersByIds(array $participants, UtilisateurRepository $utilisateurRepository): array
    {
        $userIds = array_values(array_filter(array_map(
            static fn (ConversationParticipant $participant): ?int => $participant->getUserId(),
            $participants
        )));

        if ($userIds === []) {
            return [];
        }

        $users = $utilisateurRepository->findBy(['id' => $userIds]);
        $mapped = [];
        foreach ($users as $user) {
            $id = $user->getId();
            if ($id !== null) {
                $mapped[$id] = $user;
            }
        }

        return $mapped;
    }

    private function buildUserName(?Utilisateur $user): string
    {
        if ($user === null) {
            return 'Unknown User';
        }

        $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));

        return $name !== '' ? $name : 'Unknown User';
    }

    /**
     * @param ConversationParticipant[] $participants
     */
    private function hasActorAdminPrivileges(Conversation $conversation, array $participants, int $actorUserId): bool
    {
        if ($conversation->isAdminUser($actorUserId)) {
            return true;
        }

        foreach ($participants as $participant) {
            if ((int) ($participant->getUserId() ?? 0) === $actorUserId) {
                return $participant->hasAdminPrivileges();
            }
        }

        return false;
    }

    #[Route('/apps-chat/direct-messages/candidates', name: 'apps-chat-dm-candidates', methods: ['GET'])]
    public function listDirectMessageCandidates(
        Request $request,
        ConversationRepository $conversationRepository,
        UtilisateurRepository $utilisateurRepository,
    ): JsonResponse {
        $search = trim((string) $request->query->get('q', ''));

        // Get all users
        $qb = $utilisateurRepository->createQueryBuilder('u');

        // Exclude current user
        $qb->andWhere('u.id != :currentUserId')
            ->setParameter('currentUserId', $this->currentUserId());

        // Apply search filter if provided
        if ($search !== '') {
            $qb->andWhere('LOWER(u.nom) LIKE :search OR LOWER(u.prenom) LIKE :search OR LOWER(u.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $allUsers = $qb
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        // Filter out users who already have a DM with current user
        $candidates = [];
        foreach ($allUsers as $user) {
            $userId = $user->getId();
            if ($userId !== null) {
                // Check if DM already exists between current user and this user
                $existingDM = $conversationRepository->findExistingDM($this->currentUserId(), $userId);
                if ($existingDM === null) {
                    $candidates[] = $user;
                }
            }
        }

        return $this->json([
            'success' => true,
            'candidates' => array_map(function (Utilisateur $user): array {
                return [
                    'userId' => $user->getId(),
                    'name' => $this->buildUserName($user),
                    'role' => $user->getRole() ?? 'Member',
                    'avatarSrc' => $this->toDataUri($user->getImagelink(), 'image/jpeg'),
                ];
            }, $candidates),
        ]);
    }

    #[Route('/apps-chat/groups/candidates', name: 'apps-chat-group-candidates', methods: ['GET'])]
    public function listGroupCandidates(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
    ): JsonResponse {
        $search = trim((string) $request->query->get('q', ''));

        $qb = $utilisateurRepository->createQueryBuilder('u')
            ->andWhere('u.id != :currentUserId')
            ->setParameter('currentUserId', $this->currentUserId());

        if ($search !== '') {
            $qb->andWhere('LOWER(u.nom) LIKE :search OR LOWER(u.prenom) LIKE :search OR LOWER(u.email) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        $users = $qb
            ->orderBy('u.prenom', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'candidates' => array_map(function (Utilisateur $user): array {
                return [
                    'userId' => $user->getId(),
                    'name' => $this->buildUserName($user),
                    'role' => $user->getRole() ?? 'Member',
                    'avatarSrc' => $this->toDataUri($user->getImagelink(), 'image/jpeg'),
                ];
            }, $users),
        ]);
    }

    #[Route('/apps-chat/groups/create', name: 'apps-chat-group-create', methods: ['POST'])]
    public function createGroupConversation(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $title = (string) $request->request->get('title', '');
        $memberUserIds = $this->extractUserIds($request);

        $conversation = new Conversation();
        $now = new \DateTime();
        try {
            $normalizedMemberIds = $conversation->initializeGroupConversation($this->currentUserId(), $title, $memberUserIds);
            $conversation->setLastMessageAt($now);
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        $avatar = $request->files->get('avatar');
        if ($avatar instanceof UploadedFile) {
            if (!$avatar->isValid()) {
                return $this->json([
                    'success' => false,
                    'error' => $this->formatUploadFailureMessage($avatar),
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

            try {
                $conversation->setAvatar($binary);
                $conversation->setAvatarMime($mimeType);
            } catch (InvalidArgumentException $exception) {
                return $this->json([
                    'success' => false,
                    'error' => $exception->getMessage(),
                ], 422);
            }
        }

        $knownUsers = $utilisateurRepository->findBy(['id' => $normalizedMemberIds]);
        $knownUserIds = array_values(array_map(
            static fn (Utilisateur $user): int => (int) $user->getId(),
            $knownUsers
        ));

        if (count($knownUserIds) !== count($normalizedMemberIds)) {
            return $this->json([
                'success' => false,
                'error' => 'One or more selected users were not found.',
            ], 404);
        }

        $entityManager->persist($conversation);
        $entityManager->flush();

        $conversationId = (int) ($conversation->getId() ?? 0);
        if ($conversationId <= 0) {
            return $this->json([
                'success' => false,
                'error' => 'Unable to create group right now.',
            ], 500);
        }

        $creatorParticipant = (new ConversationParticipant())
            ->setConversation_id($conversationId)
            ->setUser_id($this->currentUserId())
            ->setRole('owner')
            ->setNickname(null)
            ->setAdded_by($this->currentUserId())
            ->setJoined_at(new \DateTime())
            ->setLeft_at(null);
        $entityManager->persist($creatorParticipant);

        foreach ($normalizedMemberIds as $memberUserId) {
            $participant = (new ConversationParticipant())
                ->setConversation_id($conversationId)
                ->setUser_id((int) $memberUserId)
                ->setRole('member')
                ->setNickname(null)
                ->setAdded_by($this->currentUserId())
                ->setJoined_at(new \DateTime())
                ->setLeft_at(null);
            $entityManager->persist($participant);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'conversation' => [
                'id' => $conversationId,
                'type' => 'GROUP',
                'name' => $conversation->getTitle(),
            ],
        ], 201);
    }

    #[Route('/apps-chat/direct-messages/create', name: 'apps-chat-dm-create', methods: ['POST'])]
    public function createDirectMessage(
        Request $request,
        ConversationRepository $conversationRepository,
        UtilisateurRepository $utilisateurRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $otherUserId = (int) $request->request->get('userId', 0);

        // Validate userId
        if ($otherUserId <= 0) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid user id.',
            ], 422);
        }

        // Cannot start DM with self
        if ($otherUserId === $this->currentUserId()) {
            return $this->json([
                'success' => false,
                'error' => 'You cannot start a direct message with yourself.',
            ], 422);
        }

        // Verify other user exists
        $otherUser = $utilisateurRepository->find($otherUserId);
        if (!$otherUser instanceof Utilisateur) {
            return $this->json([
                'success' => false,
                'error' => 'User not found.',
            ], 404);
        }

        // Check if DM already exists
        $existingDM = $conversationRepository->findExistingDM($this->currentUserId(), $otherUserId);
        if ($existingDM !== null) {
            return $this->json([
                'success' => true,
                'conversation' => [
                    'id' => $existingDM->getId(),
                    'type' => 'DM',
                    'dmKey' => $existingDM->getDmKey(),
                ],
                'message' => 'Conversation already exists',
            ]);
        }

        // Create DM key: smaller_id_larger_id
        $dmKey = min($this->currentUserId(), $otherUserId) . '_' . max($this->currentUserId(), $otherUserId);

        // Create new conversation
        $conversation = new Conversation();
        $now = new \DateTime();
        $conversation->setType('DM');
        $conversation->setDmKey($dmKey);
        $conversation->setCreatedBy($this->currentUserId());
        $conversation->setCreatedAt($now);
        $conversation->setLastMessageAt($now);

        $entityManager->persist($conversation);
        $entityManager->flush();

        // Verify conversation was persisted with an ID
        $conversationId = $conversation->getId();
        if (!$conversationId) {
            throw new \RuntimeException('Failed to generate conversation ID.');
        }

        // Add participants to the conversation using the same pattern as addOrReactivateParticipant
        $participant1 = (new ConversationParticipant())
            ->setConversation_id($conversationId)
            ->setUser_id($this->currentUserId())
            ->setRole('member')
            ->setNickname(null)
            ->setAdded_by(null)
            ->setJoined_at(new \DateTime())
            ->setLeft_at(null);

        $participant2 = (new ConversationParticipant())
            ->setConversation_id($conversationId)
            ->setUser_id($otherUserId)
            ->setRole('member')
            ->setNickname(null)
            ->setAdded_by(null)
            ->setJoined_at(new \DateTime())
            ->setLeft_at(null);

        $entityManager->persist($participant1);
        $entityManager->persist($participant2);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'conversation' => [
                'id' => $conversationId,
                'type' => 'DM',
                'dmKey' => $conversation->getDmKey(),
            ],
        ], 201);
    }

    /**
     * @return int[]
     */
    private function extractUserIds(Request $request): array
    {
        $payload = $request->request->all();
        $rawValues = $payload['userIds'] ?? [];

        if (!is_array($rawValues)) {
            $csv = trim((string) $rawValues);
            if ($csv === '') {
                return [];
            }

            $rawValues = array_map('trim', explode(',', $csv));
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            $rawValues
        ), static fn (int $id): bool => $id > 0));
    }
}
