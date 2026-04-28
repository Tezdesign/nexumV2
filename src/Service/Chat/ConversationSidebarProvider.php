<?php

namespace App\Service\Chat;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\ConversationParticipant;
use App\Entity\Chat\Message;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\ConversationRepository;
use App\Repository\Chat\MessageRepository;
use App\Repository\UserHandling\UtilisateurRepository;
use DateTimeImmutable;
use DateTimeInterface;

class ConversationSidebarProvider
{
    public function __construct(
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSidebarData(int $requestedUserId): array
    {
        $currentUser = $this->utilisateurRepository->find($requestedUserId);
        $participantConversationIds = $this->participantRepository->findConversationIdsForUser($requestedUserId);
        $dmConversationIds = $this->conversationRepository->findDmConversationIdsForUser($requestedUserId);
        $conversationIds = array_values(array_unique(array_merge($participantConversationIds, $dmConversationIds)));

        return [
            'currentUser' => $this->formatCurrentUser($currentUser),
            'conversations' => $this->formatConversations($conversationIds, $requestedUserId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCurrentUser(): array
    {
        return [
            'id' => 0,
            'name' => 'Unknown User',
            'role' => 'Member',
            'avatarSrc' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCurrentUser(?Utilisateur $user): array
    {
        if ($user === null) {
            return $this->emptyCurrentUser();
        }

        $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));

        return [
            'id' => (int) ($user->getId() ?? 0),
            'name' => $name !== '' ? $name : 'Unnamed User',
            'role' => $user->getRole() ?? 'Member',
            'avatarSrc' => $this->toDataUri($user->getImagelink(), 'image/jpeg'),
        ];
    }

    /**
     * @param array<int, int> $conversationIds
     * @return array<int, array<string, mixed>>
     */
    private function formatConversations(array $conversationIds, int $userId): array
    {
        $conversations = $this->conversationRepository->findByIds($conversationIds);
        $latestMessages = $this->messageRepository->findLatestMessagesByConversationIds($conversationIds);
        $participants = $this->participantRepository->findBy([
            'conversation_id' => $conversationIds,
            'user_id' => $userId,
            'left_at' => null,
        ]);
        $conversationsById = [];
        $participantsByConversationId = [];

        foreach ($conversations as $conversation) {
            $id = $conversation->getId();
            if ($id !== null) {
                $conversationsById[$id] = $conversation;
            }
        }

        foreach ($participants as $participant) {
            $conversationId = $participant->getConversationId();
            if ($conversationId !== null) {
                $participantsByConversationId[$conversationId] = $participant;
            }
        }

        $items = [];
        foreach ($conversationIds as $conversationId) {
            $conversation = $conversationsById[$conversationId] ?? null;
            if ($conversation === null) {
                continue;
            }

            $participant = $participantsByConversationId[$conversationId] ?? null;
            $items[] = $this->formatConversation($conversation, $userId, $latestMessages[$conversationId] ?? null, $participant);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConversation(Conversation $conversation, int $userId, ?Message $latestMessage, ?ConversationParticipant $participant): array
    {
        if ($this->isDirectConversation($conversation)) {
            return $this->formatDirectConversation($conversation, $userId, $latestMessage, $participant);
        }

        $lastActivityAt = $latestMessage?->getCreatedAt() ?? $conversation->getLastMessageAt();
        $conversationId = (int) ($conversation->getId() ?? 0);
        $unreadCount = $conversationId > 0 ? $this->calculateUnreadCount($conversationId, $participant, $userId) : 0;

        return [
            'id' => $conversation->getId(),
            'name' => $conversation->getTitle() ?? 'Untitled Group',
            'avatarSrc' => $this->toDataUri($conversation->getAvatar(), $conversation->getAvatarMime() ?? 'image/jpeg'),
            'isDm' => false,
            'isAdmin' => $conversation->getCreatedBy() === $userId,
            'createdAtLabel' => $this->formatConversationCreatedAt($conversation->getCreatedAt()),
            'lastMessageAt' => $conversation->getLastMessageAt(),
            'lastMessagePreview' => $this->buildMessagePreview($latestMessage),
            'lastMessageTimeLabel' => $this->formatRelativeTimeLabel($lastActivityAt),
            'unreadCount' => $unreadCount,
        ];
    }

    private function isDirectConversation(Conversation $conversation): bool
    {
        $type = $conversation->getType();
        if ($type !== null && strcasecmp($type, 'dm') === 0) {
            return true;
        }

        return $conversation->getDmKey() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDirectConversation(Conversation $conversation, int $userId, ?Message $latestMessage, ?ConversationParticipant $participant): array
    {
        $other = $this->participantRepository->findOtherParticipant((int) $conversation->getId(), $userId);
        $otherUser = $this->findParticipantUser($other);
        $name = $this->buildDmName($otherUser, $other);
        $lastActivityAt = $latestMessage?->getCreatedAt() ?? $conversation->getLastMessageAt();
        $conversationId = (int) ($conversation->getId() ?? 0);
        $unreadCount = $conversationId > 0 ? $this->calculateUnreadCount($conversationId, $participant, $userId) : 0;

        return [
            'id' => $conversation->getId(),
            'name' => $name,
            'avatarSrc' => $otherUser !== null ? $this->toDataUri($otherUser->getImagelink(), 'image/jpeg') : null,
            'isDm' => true,
            'isAdmin' => false,
            'createdAtLabel' => $this->formatConversationCreatedAt($conversation->getCreatedAt()),
            'lastMessageAt' => $conversation->getLastMessageAt(),
            'lastMessagePreview' => $this->buildMessagePreview($latestMessage),
            'lastMessageTimeLabel' => $this->formatRelativeTimeLabel($lastActivityAt),
            'unreadCount' => $unreadCount,
        ];
    }

    private function calculateUnreadCount(int $conversationId, ?ConversationParticipant $participant, int $userId): int
    {
        $lastReadMessageId = $participant?->getLastReadMessageId();
        return $this->messageRepository->countUnreadMessages($conversationId, $lastReadMessageId, $userId);
    }

    private function buildMessagePreview(?Message $message): string
    {
        if ($message === null) {
            return 'No messages yet';
        }

        $body = trim((string) $message->getBody());
        if ($body === '') {
            return 'No text content';
        }

        $body = preg_replace('/\s+/', ' ', $body) ?? $body;
        if (mb_strlen($body) > 70) {
            return mb_substr($body, 0, 67) . '...';
        }

        return $body;
    }

    private function formatRelativeTimeLabel(?DateTimeInterface $at): string
    {
        if ($at === null) {
            return '--';
        }

        $now = new DateTimeImmutable('now', $at->getTimezone());
        $seconds = max(0, $now->getTimestamp() - $at->getTimestamp());

        if ($seconds < 60) {
            return 'now';
        }

        if ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            return $minutes . ' min';
        }

        if ($seconds < 86400) {
            $hours = (int) floor($seconds / 3600);
            return $hours . ' h';
        }

        if ($seconds < 604800) {
            $days = (int) floor($seconds / 86400);
            return $days . ' day' . ($days === 1 ? '' : 's');
        }

        if ($seconds < 1209600) {
            return '1 week ago';
        }

        return $at->format('M j');
    }

    private function formatConversationCreatedAt(?DateTimeInterface $at): string
    {
        if ($at === null) {
            return '--';
        }

        return $at->format('M j, Y g:ia');
    }

    private function findParticipantUser(?ConversationParticipant $participant): ?Utilisateur
    {
        if ($participant === null) {
            return null;
        }

        $userId = $participant->getUserId();
        if ($userId === null) {
            return null;
        }

        return $this->utilisateurRepository->find($userId);
    }

    private function buildDmName(?Utilisateur $user, ?ConversationParticipant $participant): string
    {
        if ($participant !== null && $participant->getNickname() !== null) {
            $nickname = trim((string) $participant->getNickname());
            if ($nickname !== '') {
                return $nickname;
            }
        }

        if ($user !== null) {
            $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));
            if ($name !== '') {
                return $name;
            }
        }

        return 'Unknown User';
    }

    private function toDataUri(mixed $blobValue, string $mime): ?string
    {
        $binary = $this->blobToString($blobValue);
        if ($binary === null || $binary === '') {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode($binary));
    }

    private function blobToString(mixed $blobValue): ?string
    {
        if ($blobValue === null) {
            return null;
        }

        if (is_resource($blobValue)) {
            $value = stream_get_contents($blobValue);
            return $value === false ? null : $value;
        }

        if (is_string($blobValue)) {
            return $blobValue;
        }

        return null;
    }
}
