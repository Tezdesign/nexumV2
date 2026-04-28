<?php

namespace App\Entity\Chat;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Chat\MessageRepository;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: "idx_messages_conv_id", columns: ["conversation_id"])]
#[ORM\Index(name: "idx_messages_conv_time", columns: ["conversation_id", "created_at"])]
#[ORM\Index(name: "idx_messages_sender_time", columns: ["sender_id", "created_at"])]
class Message
{
    public const MAX_TEXT_BODY_LENGTH = 300;

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

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $conversation_id = null;

    public function getConversation_id(): ?int
    {
        return $this->conversation_id;
    }

    public function setConversation_id(int $conversation_id): self
    {
        $this->conversation_id = $conversation_id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $sender_id = null;

    public function getSender_id(): ?int
    {
        return $this->sender_id;
    }

    public function setSender_id(int $sender_id): self
    {
        $this->sender_id = $sender_id;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $body = null;

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $normalizedBody = trim($body);
        if ($normalizedBody === '') {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }

        if (mb_strlen($normalizedBody) > self::MAX_TEXT_BODY_LENGTH) {
            throw new \InvalidArgumentException('Message must not exceed 300 characters.');
        }

        $this->body = $normalizedBody;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $kind = null;

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;
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

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $edited_at = null;

    public function getEdited_at(): ?\DateTimeInterface
    {
        return $this->edited_at;
    }

    public function setEdited_at(?\DateTimeInterface $edited_at): self
    {
        $this->edited_at = $edited_at;
        return $this;
    }

    public function getConversationId(): ?int
    {
        return $this->conversation_id;
    }

    public function setConversationId(int $conversation_id): static
    {
        $this->conversation_id = $conversation_id;

        return $this;
    }

    public function getSenderId(): ?int
    {
        return $this->sender_id;
    }

    public function setSenderId(int $sender_id): static
    {
        $this->sender_id = $sender_id;

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

    public function getEditedAt(): ?\DateTimeInterface
    {
        return $this->edited_at;
    }

    public function setEditedAt(?\DateTime $edited_at): static
    {
        $this->edited_at = $edited_at;

        return $this;
    }

}
