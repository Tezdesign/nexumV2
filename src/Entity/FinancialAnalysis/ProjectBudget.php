<?php

namespace App\Entity\FinancialAnalysis;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\FinancialAnalysis\Transaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ProjectBudgetRepository::class)]
#[ORM\Table(name: 'project_budget')]
#[ORM\Index(name: "fk_pro_id", columns: ["projectId"])]
class ProjectBudget
{
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
    #[Assert\NotBlank(message: "enter the name of the budget")]
    #[Assert\Regex(pattern: "/^[a-zA-Z ]+$/",message: "The name can only contain letters and spaces")]
    #[Assert\Length(min: 3, minMessage: "The name must at least 3 characters long.")]
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    #[Assert\NotBlank(message: "enter the budget for the project")]
    #[Assert\Positive(message: "The budget must be greater than zero.")]
    private ?string $total_budget = null;



    #[ORM\Column(name: 'actualSpend', type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private ?string $actualSpend = null;

    public function getActualSpend(): ?string
    {
        return $this->actualSpend;
    }

    public function setActualSpend(string $actualSpend): self
    {
        $this->actualSpend = $actualSpend;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $status = "ON TRACK";

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    #[ORM\Column(name: 'dueDate', type: 'date', nullable: false)]
    #[Assert\NotBlank(message: "A due date is required.")]
    private ?\DateTimeInterface $dueDate = null;

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: \App\Entity\Projects\Project::class)]
    #[ORM\JoinColumn(name: 'projectId', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotBlank(message: "You must select a project.")]
    private ?\App\Entity\Projects\Project $project = null;

    public function getProject(): ?\App\Entity\Projects\Project
    {
        return $this->project;
    }

    public function setProject(?\App\Entity\Projects\Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'projectBudget')]
    private Collection $transactions;

    // Transient attributes for validation (not mapped to DB)
    private ?\DateTimeInterface $transientFiscalStart = null;
    private ?\DateTimeInterface $transientFiscalEnd = null;

    public function setTransientFiscalStart(?\DateTimeInterface $start): self
    {
        $this->transientFiscalStart = $start;
        return $this;
    }

    public function setTransientFiscalEnd(?\DateTimeInterface $end): self
    {
        $this->transientFiscalEnd = $end;
        return $this;
    }

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->actualSpend = '0.00';
        $this->status = 'ON TRACK';
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        if (!$this->transactions instanceof Collection) {
            $this->transactions = new ArrayCollection();
        }
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): self
    {
        if (!$this->getTransactions()->contains($transaction)) {
            $this->getTransactions()->add($transaction);
        }
        return $this;
    }

    public function removeTransaction(Transaction $transaction): self
    {
        $this->getTransactions()->removeElement($transaction);
        return $this;
    }

    public function getTotalBudget(): ?string
    {
        return $this->total_budget;
    }

    public function setTotalBudget(string $total_budget): static
    {
        $this->total_budget = $total_budget;

        return $this;
    }

    #[Assert\Callback]
    public function validateProjectLogic(ExecutionContextInterface $context, mixed $payload): void
    {

        if ($this->dueDate && $this->project && $this->project->getEndDate()) {

            $budgetDate = $this->dueDate->format('Y-m-d');
            $projectEndDate = $this->project->getEndDate()->format('Y-m-d');

            if ($budgetDate > $projectEndDate) {
                $context->buildViolation('The budget due date cannot be later than the project end date (' . $projectEndDate . ').')
                    ->atPath('dueDate')
                    ->addViolation();
            }
        }

        if ($this->dueDate && $this->transientFiscalStart && $this->transientFiscalEnd) {
            $budgetDate = $this->dueDate->format('Y-m-d');
            $fStart = $this->transientFiscalStart->format('Y-m-d');
            $fEnd = $this->transientFiscalEnd->format('Y-m-d');

            if ($budgetDate < $fStart || $budgetDate > $fEnd) {
                $context->buildViolation('The budget due date must fall within the Fiscal Year (' . $fStart . ' to ' . $fEnd . ').')
                    ->atPath('dueDate')
                    ->addViolation();
            }
        }
    }
}

