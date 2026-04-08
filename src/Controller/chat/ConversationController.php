<?php

namespace App\Controller\chat;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\ConversationParticipant;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\ConversationRepository;
use App\Repository\UserHandling\UtilisateurRepository;
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
    public const SESSION_CURRENT_USER_ID = 53;

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
        try {
            $conversation->renameBy(self::SESSION_CURRENT_USER_ID, $title);
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

        try {
            $conversation->updateAvatarBy(self::SESSION_CURRENT_USER_ID, $binary, $mimeType);
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

        try {
            $conversation->assertGroupConversation();
        } catch (InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        $participants = $this->findActiveParticipants($participantRepository, $conversationId);
        $usersById = $this->mapUsersByIds($participants, $utilisateurRepository);
        $actorIsAdmin = $conversation->isAdminUser(self::SESSION_CURRENT_USER_ID);

        return $this->json([
            'success' => true,
            'members' => array_map(function (ConversationParticipant $participant) use ($conversation, $usersById, $actorIsAdmin): array {
                $userId = (int) ($participant->getUserId() ?? 0);
                $user = $usersById[$userId] ?? null;
                $isSelf = $userId === self::SESSION_CURRENT_USER_ID;
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
                    'canKick' => $actorIsAdmin && !$isSelf,
                ];
            }, $participants),
            'actorIsAdmin' => $actorIsAdmin,
            'canAddMembers' => true,
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
        EntityManagerInterface $entityManager,
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

        $existing = $participantRepository->findOneBy([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'left_at' => null,
        ]);
        if ($existing instanceof ConversationParticipant) {
            return $this->json([
                'success' => false,
                'error' => 'This user is already in the conversation.',
            ], 422);
        }

        $user = $utilisateurRepository->find($userId);
        if (!$user instanceof Utilisateur) {
            return $this->json([
                'success' => false,
                'error' => 'User not found.',
            ], 404);
        }

        $participant = (new ConversationParticipant())
            ->setConversation_id($conversationId)
            ->setUser_id($userId)
            ->setRole('member')
            ->setNickname(null)
            ->setAdded_by(self::SESSION_CURRENT_USER_ID)
            ->setJoined_at(new \DateTimeImmutable())
            ->setLeft_at(null);

        $entityManager->persist($participant);
        $entityManager->flush();

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
            $conversation->assertCanRenameParticipant(self::SESSION_CURRENT_USER_ID, $userId);
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

        try {
            $conversation->assertCanKickParticipant(self::SESSION_CURRENT_USER_ID, $userId);
            $participant->leaveConversation();
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
}
