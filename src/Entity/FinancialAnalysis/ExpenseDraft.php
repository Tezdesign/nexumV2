<?php

namespace App\Entity\FinancialAnalysis;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\FinancialAnalysis\ExpenseDraftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpenseDraftRepository::class)]
class ExpenseDraft
{


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supabaseId = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'expenseDrafts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProjectBudget $project_budget_related = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupabaseId(): ?string
    {
        return $this->supabaseId;
    }

    public function setSupabaseId(?string $supabaseId): static
    {
        $this->supabaseId = $supabaseId;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCreatedBy(): ?Utilisateur
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getProjectBudgetRelated(): ?ProjectBudget
    {
        return $this->project_budget_related;
    }

    public function setProjectBudgetRelated(?ProjectBudget $project_budget_related): static
    {
        $this->project_budget_related = $project_budget_related;

        return $this;
    }

    public function __construct()
    {
        $this->status = 'PENDING';
        $this->createdAt = new \DateTimeImmutable();
    }
}
