<?php

namespace App\Entity\FinancialAnalysis;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: BudgetProfileRepository::class)]
#[ORM\Table(name: 'budget_profile')]
#[ORM\Index(name: "fiscal_year", columns: ["fiscal_year"])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['fiscal_year'], message: 'A budget profile for this fiscal year already exists.')]
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
    #[Assert\NotBlank(message: "select the fiscal year within a 10-year range")]
    #[Assert\Regex(pattern: "/^\d{4}$/", message: " either select or type a valid year  within a 10-year range")]
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
    #[Assert\NotBlank(message: 'The budget field is can not be blank.')]
    #[Assert\GreaterThanOrEqual(value: 10000, message:"the budget must be mininum 10000 ")]
    #[Assert\DivisibleBy(value: 10, message: 'The budget field is divisible by 10')]
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

    private ?float $transientAllocatedBudgets = null;
    private ?float $transientProjectExpenses = null;

    public function setTransientAllocatedBudgets(?float $allocated): self
    {
        $this->transientAllocatedBudgets = $allocated;
        return $this;
    }

    public function setTransientProjectExpenses(?float $expenses): self
    {
        $this->transientProjectExpenses = $expenses;
        return $this;
    }

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

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    #[Assert\NotBlank(message: 'Type or select your currency')]
    #[Assert\Regex(pattern: "/^[A-Z]{3}$/", message: "type or select a valid recognized currency")]
    private ?string $base_currency = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\NotBlank(message: 'fill the staring date.')]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\NotBlank(message: 'fill the end date.')]
    private ?\DateTimeInterface $end_date = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false, options: ['default' => 'DRAFT'])]
    private ?string $status = 'DRAFT';

    public function __construct()
    {

        $this->total_expense = '0.00';
        $this->margin_profit = 100.00;
    }

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

    public function getBaseCurrency(): ?string
    {
        return $this->base_currency;
    }

    public function setBaseCurrency(?string $base_currency): static
    {
        $this->base_currency = $base_currency;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->end_date;
    }

    public function setEndDate(?\DateTimeInterface $end_date): static
    {
        $this->end_date = $end_date;

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
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateStatus(): void
    {
        if ($this->fiscal_year) {
            $currentYear = date('Y');

            if ($this->fiscal_year === $currentYear) {
                $this->status = 'ACTIVE';
            } else {
                $this->status = 'DRAFT';
            }
        }
    }
    #[Assert\Callback]
    public function validateBusinessLogic(ExecutionContextInterface $context, mixed $payload): void
    {
        if ($this->fiscal_year) {
            $currentYear = (int) date('Y');
            $inputYear = (int) $this->fiscal_year;

            if ($inputYear < ($currentYear - 10) || $inputYear > ($currentYear + 10)) {
                $context->buildViolation('The fiscal year must fall within a 10-year range.')
                    ->atPath('fiscal_year')
                    ->addViolation();
            }
        }

        if ($this->start_date && $this->end_date) {
            // Ensure the start date actually begins within the chosen fiscal year
            if ($this->fiscal_year && $this->start_date->format('Y') !== $this->fiscal_year) {
                $context->buildViolation('The custom start date must begin within the selected fiscal year (' . $this->fiscal_year . ').')
                    ->atPath('start_date')
                    ->addViolation();
            }

            // Safely convert to a mutable DateTime to avoid DateTimeImmutable bugs
            $expectedEndDateMinusOneDay = new \DateTime($this->start_date->format('Y-m-d'));
            $expectedEndDateMinusOneDay->modify('+1 year')->modify('-1 day');
            
            $expectedEndDateExact = new \DateTime($this->start_date->format('Y-m-d'));
            $expectedEndDateExact->modify('+1 year');

            $actualEnd = $this->end_date->format('Y-m-d');

            if ($this->end_date->format('Y-m-d') !== $expectedEndDateMinusOneDay->format('Y-m-d') && $actualEnd !== $expectedEndDateExact->format('Y-m-d')) {
                $context->buildViolation('The budget period must be exactly 12 months long (e.g., ' . $this->start_date->format('Y-m-d') . ' to ' . $expectedEndDateMinusOneDay->format('Y-m-d') . ').')
                    ->atPath('end_date')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    public function validateUpdateLogic(ExecutionContextInterface $context, mixed $payload): void
    {
        // The 110 Rule: If the new value of the budget_disposable is less than the sum of allocated project budgets
        // or the current expenses (sum of project budget expenses within the same fiscal year + 10%),
        // the update must be denied.
        
        $newBudget = (float) $this->budget_disposable;
        
        if ($this->transientAllocatedBudgets !== null && $newBudget < $this->transientAllocatedBudgets) {
            $context->buildViolation('The new budget cannot be less than the total allocated project budgets ($' . number_format($this->transientAllocatedBudgets, 2) . ').')
                ->atPath('budget_disposable')
                ->addViolation();
        }
        
        if ($this->transientProjectExpenses !== null) {
            $minimumRequired = $this->transientProjectExpenses * 1.10; // Expenses + 10%
            if ($newBudget < $minimumRequired) {
                $context->buildViolation('The new budget cannot be less than current expenses plus 10% ($' . number_format($minimumRequired, 2) . ') due to the 110 Rule.')
                    ->atPath('budget_disposable')
                    ->addViolation();
            }
        }
    }
}

