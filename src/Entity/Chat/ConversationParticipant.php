<?php

namespace App\Entity\Chat;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\Chat\ConversationParticipantRepository;

#[ORM\Entity(repositoryClass: ConversationParticipantRepository::class)]
#[ORM\Table(name: 'conversation_participants')]
class ConversationParticipant
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
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

    #[ORM\Id]
    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $user_id = null;

    public function getUser_id(): ?int
    {
        return $this->user_id;
    }

    public function setUser_id(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $nickname = null;

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $added_by = null;

    public function getAdded_by(): ?int
    {
        return $this->added_by;
    }

    public function setAdded_by(?int $added_by): self
    {
        $this->added_by = $added_by;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $joined_at = null;

    public function getJoined_at(): ?\DateTimeInterface
    {
        return $this->joined_at;
    }

    public function setJoined_at(\DateTimeInterface $joined_at): self
    {
        $this->joined_at = $joined_at;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $left_at = null;

    public function getLeft_at(): ?\DateTimeInterface
    {
        return $this->left_at;
    }

    public function setLeft_at(?\DateTimeInterface $left_at): self
    {
        $this->left_at = $left_at;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $last_read_message_id = null;

    public function getLast_read_message_id(): ?int
    {
        return $this->last_read_message_id;
    }

    public function setLast_read_message_id(?int $last_read_message_id): self
    {
        $this->last_read_message_id = $last_read_message_id;
        return $this;
    }

    public function getConversationId(): ?int
    {
        return $this->conversation_id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getAddedBy(): ?int
    {
        return $this->added_by;
    }

    public function setAddedBy(?int $added_by): static
    {
        $this->added_by = $added_by;

        return $this;
    }

    public function getJoinedAt(): ?\DateTime
    {
        return $this->joined_at;
    }

    public function setJoinedAt(\DateTime $joined_at): static
    {
        $this->joined_at = $joined_at;

        return $this;
    }

    public function getLeftAt(): ?\DateTime
    {
        return $this->left_at;
    }

    public function setLeftAt(?\DateTime $left_at): static
    {
        $this->left_at = $left_at;

        return $this;
    }

    public function getLastReadMessageId(): ?int
    {
        return $this->last_read_message_id;
    }

    public function setLastReadMessageId(?int $last_read_message_id): static
    {
        $this->last_read_message_id = $last_read_message_id;

        return $this;
    }

}
