<?php

namespace App\Entity\FinancialAnalysis;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\FinancialAnalysis\BudgetProfileRepository;

#[ORM\Entity(repositoryClass: BudgetProfileRepository::class)]
#[ORM\Table(name: 'budget_profile')]
#[ORM\Index(name: "fiscal_year", columns: ["fiscal_year"])]
class BudgetProfile
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
    private ?string $fiscal_year = null;

    public function getFiscal_year(): ?string
    {
        return $this->fiscal_year;
    }

    public function setFiscal_year(string $fiscal_year): self
    {
        $this->fiscal_year = $fiscal_year;
        return $this;
    }

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private ?string $budget_disposable = null;

    public function getBudget_disposable(): ?string
    {
        return $this->budget_disposable;
    }

    public function setBudget_disposable(string $budget_disposable): self
    {
        $this->budget_disposable = $budget_disposable;
        return $this;
    }

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $total_expense = null;

    public function getTotal_expense(): ?string
    {
        return $this->total_expense;
    }

    public function setTotal_expense(?string $total_expense): self
    {
        $this->total_expense = $total_expense;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $margin_profit = null;

    public function getMargin_profit(): ?float
    {
        return $this->margin_profit;
    }

    public function setMargin_profit(?float $margin_profit): self
    {
        $this->margin_profit = $margin_profit;
        return $this;
    }

    public function getFiscalYear(): ?string
    {
        return $this->fiscal_year;
    }

    public function setFiscalYear(string $fiscal_year): static
    {
        $this->fiscal_year = $fiscal_year;

        return $this;
    }

    public function getBudgetDisposable(): ?string
    {
        return $this->budget_disposable;
    }

    public function setBudgetDisposable(string $budget_disposable): static
    {
        $this->budget_disposable = $budget_disposable;

        return $this;
    }

    public function getTotalExpense(): ?string
    {
        return $this->total_expense;
    }

    public function setTotalExpense(?string $total_expense): static
    {
        $this->total_expense = $total_expense;

        return $this;
    }

    public function getMarginProfit(): ?float
    {
        return $this->margin_profit;
    }

    public function setMarginProfit(?float $margin_profit): static
    {
        $this->margin_profit = $margin_profit;

        return $this;
    }

}

