<?php

namespace App\Service\Chat;

use App\Entity\Chat\Conversation;
use App\Entity\Chat\ConversationParticipant;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\Chat\ConversationParticipantRepository;
use App\Repository\Chat\ConversationRepository;
use App\Repository\UserHandling\UtilisateurRepository;

class ConversationSidebarProvider
{
    public function __construct(
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function getSidebarData(int $requestedUserId): array
    {
        $currentUser = $this->utilisateurRepository->find($requestedUserId);
        $conversationIds = $this->participantRepository->findConversationIdsForUser($requestedUserId);

        return [
            'currentUser' => $this->formatCurrentUser($currentUser),
            'conversations' => $this->formatConversations($conversationIds, $requestedUserId),
        ];
    }

    private function emptyCurrentUser(): array
    {
        return [
            'name' => 'Unknown User',
            'role' => 'Member',
            'avatarSrc' => null,
        ];
    }

    private function formatCurrentUser(?Utilisateur $user): array
    {
        if ($user === null) {
            return $this->emptyCurrentUser();
        }

        $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));

        return [
            'name' => $name !== '' ? $name : 'Unnamed User',
            'role' => $user->getRole() ?? 'Member',
            'avatarSrc' => $this->toDataUri($user->getImagelink(), 'image/jpeg'),
        ];
    }

    /**
     * @param int[] $conversationIds
     */
    private function formatConversations(array $conversationIds, int $userId): array
    {
        $conversations = $this->conversationRepository->findByIds($conversationIds);
        $conversationsById = [];

        foreach ($conversations as $conversation) {
            $id = $conversation->getId();
            if ($id !== null) {
                $conversationsById[$id] = $conversation;
            }
        }

        $items = [];
        foreach ($conversationIds as $conversationId) {
            $conversation = $conversationsById[$conversationId] ?? null;
            if ($conversation === null) {
                continue;
            }

            $items[] = $this->formatConversation($conversation, $userId);
        }

        return $items;
    }

    private function formatConversation(Conversation $conversation, int $userId): array
    {
        if ($this->isDirectConversation($conversation)) {
            return $this->formatDirectConversation($conversation, $userId);
        }

        return [
            'id' => $conversation->getId(),
            'name' => $conversation->getTitle() ?? 'Untitled Group',
            'avatarSrc' => $this->toDataUri($conversation->getAvatar(), $conversation->getAvatarMime() ?? 'image/jpeg'),
            'isDm' => false,
            'lastMessageAt' => $conversation->getLastMessageAt(),
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

    private function formatDirectConversation(Conversation $conversation, int $userId): array
    {
        $other = $this->participantRepository->findOtherParticipant((int) $conversation->getId(), $userId);
        $otherUser = $this->findParticipantUser($other);
        $name = $this->buildDmName($otherUser, $other);

        return [
            'id' => $conversation->getId(),
            'name' => $name,
            'avatarSrc' => $otherUser !== null ? $this->toDataUri($otherUser->getImagelink(), 'image/jpeg') : null,
            'isDm' => true,
            'lastMessageAt' => $conversation->getLastMessageAt(),
        ];
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
        if ($user !== null) {
            $name = trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom()));
            if ($name !== '') {
                return $name;
            }
        }

        if ($participant !== null && $participant->getNickname() !== null) {
            return $participant->getNickname();
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
