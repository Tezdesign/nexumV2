<?php

namespace App\Entity\Chat;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

use App\Repository\Chat\ConversationRepository;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversations')]
#[ORM\Index(name: "idx_conversations_created_by", columns: ["created_by"])]
#[ORM\Index(name: "idx_conversations_last", columns: ["last_message_id"])]
#[ORM\UniqueConstraint(name: "uq_conversations_dm_key", columns: ["dm_key"])]
class Conversation
{
    public const MAX_AVATAR_BLOB_BYTES = 2097152;
    public const GROUP_NAME_MIN_LENGTH = 4;
    public const GROUP_NAME_MAX_LENGTH = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $title = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $normalizedTitle = $title !== null ? trim($title) : null;
        if ($normalizedTitle === '') {
            throw new InvalidArgumentException('Chat name cannot be empty.');
        }

        if ($normalizedTitle !== null) {
            $length = mb_strlen($normalizedTitle);
            if ($length < self::GROUP_NAME_MIN_LENGTH || $length > self::GROUP_NAME_MAX_LENGTH) {
                throw new InvalidArgumentException('Chat name must be between 4 and 7 letters.');
            }

            if (!preg_match('/^[A-Za-z]+$/', $normalizedTitle)) {
                throw new InvalidArgumentException('Chat name can contain letters only.');
            }
        }

        $this->title = $normalizedTitle;
        return $this;
    }

    /**
     * @param int[] $memberUserIds Users to invite (excluding creator).
     *
     * @return int[] Normalized unique invited user IDs.
     */
    public function initializeGroupConversation(int $creatorUserId, string $title, array $memberUserIds): array
    {
        if ($creatorUserId <= 0) {
            throw new InvalidArgumentException('Invalid creator id.');
        }

        $normalizedMemberIds = [];
        foreach ($memberUserIds as $memberUserId) {
            $id = (int) $memberUserId;
            if ($id <= 0 || $id === $creatorUserId) {
                continue;
            }

            $normalizedMemberIds[$id] = $id;
        }

        if (count($normalizedMemberIds) < 2) {
            throw new InvalidArgumentException('A group conversation must include at least 3 people.');
        }

        $this->setType('GROUP');
        $this->setDmKey(null);
        $this->setTitle($title);
        $this->setCreatedBy($creatorUserId);
        $this->setCreatedAt(new \DateTime());
        $this->setLastMessageId(null);
        $this->setLastMessageAt(null);

        return array_values($normalizedMemberIds);
    }

    public function renameBy(int $userId, string $title): self
    {
        $this->assertCanBeCustomizedBy($userId);
        $this->setTitle($title);

        return $this;
    }

    public function updateAvatarBy(int $userId, string $avatar, string $mimeType): self
    {
        $this->assertCanBeCustomizedBy($userId);
        $this->setAvatar($avatar);
        $this->setAvatarMime($mimeType);

        return $this;
    }

    public function assertCanBeCustomizedBy(int $userId): void
    {
        if (!$this->isGroupConversation()) {
            throw new InvalidArgumentException('Only group chats can be customized.');
        }

        if ((int) $this->getCreatedBy() !== $userId) {
            throw new InvalidArgumentException('Only the chat owner can modify this discussion.');
        }
    }

    public function assertGroupConversation(): void
    {
        if (!$this->isGroupConversation()) {
            throw new InvalidArgumentException('Only group chats support this action.');
        }
    }

    public function isGroupConversation(): bool
    {
        return !$this->isDirectConversation();
    }

    public function isAdminUser(int $userId): bool
    {
        return (int) $this->getCreatedBy() === $userId;
    }

    public function assertCanKickParticipant(int $actorUserId, int $targetUserId, bool $actorHasAdminPrivileges = false): void
    {
        $this->assertGroupConversation();

        if (!$this->isAdminUser($actorUserId) && !$actorHasAdminPrivileges) {
            throw new InvalidArgumentException('Only the group admin can kick members.');
        }

        if ($actorUserId === $targetUserId) {
            throw new InvalidArgumentException('You cannot kick yourself.');
        }
    }

    public function assertCanRenameParticipant(int $actorUserId, int $targetUserId): void
    {
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            throw new InvalidArgumentException('Invalid participant id.');
        }
    }

    private function isDirectConversation(): bool
    {
        $type = $this->getType();
        if ($type !== null && strcasecmp($type, 'dm') === 0) {
            return true;
        }

        return $this->getDmKey() !== null;
    }

    #[ORM\Column(type: 'blob', nullable: true)]
    private mixed $avatar = null;

    public function getAvatar(): ?string
    {
        if ($this->avatar === null) {
            return null;
        }

        if (is_resource($this->avatar)) {
            $value = stream_get_contents($this->avatar);
            return $value === false ? null : $value;
        }

        return is_string($this->avatar) ? $this->avatar : null;
    }

    public function setAvatar(?string $avatar): self
    {
        if ($avatar !== null && strlen($avatar) > self::MAX_AVATAR_BLOB_BYTES) {
            throw new InvalidArgumentException('Chat image is too large. Maximum size is 2 MB.');
        }

        $this->avatar = $avatar;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $avatar_mime = null;

    public function getAvatar_mime(): ?string
    {
        return $this->avatar_mime;
    }

    public function setAvatar_mime(?string $avatar_mime): self
    {
        $this->avatar_mime = $avatar_mime;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $created_by = null;

    public function getCreated_by(): ?int
    {
        return $this->created_by;
    }

    public function setCreated_by(?int $created_by): self
    {
        $this->created_by = $created_by;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $dm_key = null;

    public function getDm_key(): ?string
    {
        return $this->dm_key;
    }

    public function setDm_key(?string $dm_key): self
    {
        $this->dm_key = $dm_key;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $last_message_id = null;

    public function getLast_message_id(): ?int
    {
        return $this->last_message_id;
    }

    public function setLast_message_id(?int $last_message_id): self
    {
        $this->last_message_id = $last_message_id;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_message_at = null;

    public function getLast_message_at(): ?\DateTimeInterface
    {
        return $this->last_message_at;
    }

    public function setLast_message_at(?\DateTimeInterface $last_message_at): self
    {
        $this->last_message_at = $last_message_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getAvatarMime(): ?string
    {
        return $this->avatar_mime;
    }

    public function setAvatarMime(?string $avatar_mime): static
    {
        if ($avatar_mime !== null && $avatar_mime !== '' && !str_starts_with($avatar_mime, 'image/')) {
            throw new InvalidArgumentException('Only image files are allowed.');
        }

        $this->avatar_mime = $avatar_mime;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->created_by;
    }

    public function setCreatedBy(?int $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getDmKey(): ?string
    {
        return $this->dm_key;
    }

    public function setDmKey(?string $dm_key): static
    {
        $this->dm_key = $dm_key;

        return $this;
    }

    public function getLastMessageId(): ?int
    {
        return $this->last_message_id;
    }

    public function setLastMessageId(?int $last_message_id): static
    {
        $this->last_message_id = $last_message_id;

        return $this;
    }

    public function getLastMessageAt(): ?\DateTimeInterface
    {
        return $this->last_message_at;
    }

    public function setLastMessageAt(?\DateTimeInterface $last_message_at): static
    {
        $this->last_message_at = $last_message_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

}
