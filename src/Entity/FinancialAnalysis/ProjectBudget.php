<?php

namespace App\Entity\FinancialAnalysis;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\FinancialAnalysis\Transaction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\FinancialAnalysis\ProjectBudgetRepository;

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
    private ?string $total_budget = null;

    public function getTotal_budget(): ?string
    {
        return $this->total_budget;
    }

    public function setTotal_budget(string $total_budget): self
    {
        $this->total_budget = $total_budget;
        return $this;
    }

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
    private ?string $status = null;

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
    private ?\DateTimeInterface $dueDate = null;

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    #[ORM\Column(name: 'projectId', type: 'integer', nullable: false)]
    private ?int $projectId = null;

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(int $projectId): self
    {
        $this->projectId = $projectId;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'projectBudget')]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
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

}

