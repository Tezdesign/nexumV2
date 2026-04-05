<?php

namespace App\Entity\FinancialAnalysis;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\FinancialAnalysis\ProjectBudget;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\FinancialAnalysis\TransactionRepository;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
class Transaction
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

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $reference = null;

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private ?string $cost = null;

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(string $cost): self
    {
        $this->cost = $cost;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_stamp = null;

    public function getDate_stamp(): ?\DateTimeInterface
    {
        return $this->date_stamp;
    }

    public function setDate_stamp(\DateTimeInterface $date_stamp): self
    {
        $this->date_stamp = $date_stamp;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $expense_category = null;

    public function getExpense_category(): ?string
    {
        return $this->expense_category;
    }

    public function setExpense_category(?string $expense_category): self
    {
        $this->expense_category = $expense_category;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: ProjectBudget::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'project_budget_id', referencedColumnName: 'id')]
    private ?ProjectBudget $projectBudget = null;

    public function getProjectBudget(): ?ProjectBudget
    {
        return $this->projectBudget;
    }

    public function setProjectBudget(?ProjectBudget $projectBudget): self
    {
        $this->projectBudget = $projectBudget;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $description = null;

    public function getDescription(): ?int
    {
        return $this->description;
    }

    public function setDescription(int $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDateStamp(): ?\DateTime
    {
        return $this->date_stamp;
    }

    public function setDateStamp(\DateTime $date_stamp): static
    {
        $this->date_stamp = $date_stamp;

        return $this;
    }

    public function getExpenseCategory(): ?string
    {
        return $this->expense_category;
    }

    public function setExpenseCategory(?string $expense_category): static
    {
        $this->expense_category = $expense_category;

        return $this;
    }

}
