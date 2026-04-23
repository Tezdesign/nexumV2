<?php

namespace App\Entity\FinancialAnalysis;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "sync_log")]
class SyncLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $entityClass = null;

    #[ORM\Column]
    private ?int $entityId = null;

    #[ORM\Column(length: 50)]
    private ?string $actionType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    
    public function getEntityClass(): ?string { return $this->entityClass; }
    public function setEntityClass(string $entityClass): self { $this->entityClass = $entityClass; return $this; }

    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(int $entityId): self { $this->entityId = $entityId; return $this; }

    public function getActionType(): ?string { return $this->actionType; }
    public function setActionType(string $actionType): self { $this->actionType = $actionType; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}