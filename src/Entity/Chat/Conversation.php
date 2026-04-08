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

        if ($normalizedTitle !== null && mb_strlen($normalizedTitle) < 3) {
            throw new InvalidArgumentException('Chat name must contain at least 3 characters.');
        }

        $this->title = $normalizedTitle;
        return $this;
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
        if ($this->isDirectConversation()) {
            throw new InvalidArgumentException('Only group chats can be customized.');
        }

        if ((int) $this->getCreatedBy() !== $userId) {
            throw new InvalidArgumentException('Only the chat owner can modify this discussion.');
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
    private $avatar = null;

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

    public function getLastMessageAt(): ?\DateTime
    {
        return $this->last_message_at;
    }

    public function setLastMessageAt(?\DateTime $last_message_at): static
    {
        $this->last_message_at = $last_message_at;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

}
