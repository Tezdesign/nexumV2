<?php

namespace App\Entity\FinancialAnalysis;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\FinancialAnalysis\ExpenseDraftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
    #[Assert\NotBlank(message: 'The amount must be specified.')]
    #[Assert\Positive(message: 'The amount must be greater than zero.')]
    private ?float $amount = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Please provide a description.')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please select a category.')]
    #[Assert\Choice(callback: 'getValidCategories', message: 'Select a valid category.')]
    private ?string $category = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'expenseDrafts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'Please select a project budget.')]
    private ?ProjectBudget $project_budget_related = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please provide a subject.')]
    #[Assert\Length(max: 255, maxMessage: 'The subject cannot be longer than {{ limit }} characters.')]
    private ?string $subject = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type:'json', nullable: true)]
    private ?array $evalData = null;



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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public static function getValidCategories(): array
    {
        return [
            'HARDWARE',
            'SOFTWARE',
            'SERVICES',
            'TRAVEL',
            'MARKETING',
            'OTHER'
        ];
    }

    #[Assert\Callback]
    public function validateBudget(ExecutionContextInterface $context, mixed $payload): void
    {
        if ($this->project_budget_related) {
            $budget = $this->project_budget_related;
            
            if ($budget->getStatus() === 'CLOSED') {
                $context->buildViolation('Cannot add drafts to a closed budget.')
                    ->atPath('project_budget_related')
                    ->addViolation();
            }

            if ($this->amount !== null) {
                $totalBudget = (float) $budget->getTotalBudget();
                $actualSpend = (float) $budget->getActualSpend();
                $remaining = $totalBudget - $actualSpend;

                if ($this->amount > $remaining) {
                    $context->buildViolation('The draft amount ($' . number_format($this->amount, 2) . ') exceeds the remaining budget ($' . number_format($remaining, 2) . ').')
                        ->atPath('amount')
                        ->addViolation();
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEvalData(): ?array
    {
        return $this->evalData;
    }

    /**
     * @param array<string, mixed>|null $evalData
     */
    public function setEvalData(?array $evalData): self
    {
        $this->evalData = $evalData;

        return $this;
    }
}
