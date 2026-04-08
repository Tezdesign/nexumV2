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

#[ORM\Entity(repositoryClass: BudgetProfileRepository::class)]
#[ORM\Table(name: 'budget_profile')]
#[ORM\Index(name: "fiscal_year", columns: ["fiscal_year"])]
#[ORM\HasLifecycleCallbacks]
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
    #[Assert\Regex("/^\[A-Z]{3}$/", message: "type or select a valid recognized currency")]
    private ?string $base_currency = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $end_date = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false, options: ['default' => 'DRAFT'])]
    private ?string $status = 'DRAFT';

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
        // Automatically determine if the budget is DRAFT or ACTIVE based on the fiscal year
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

            if ($inputYear < ($currentYear - 10)) {
                $context->buildViolation('The fiscal year must fall within the last 10 years.')
                    ->atPath('fiscal_year')
                    ->addViolation();
            }
            else {
                $expectedEndDate = clone $this->start_date;
                $expectedEndDate->modify('+1 year');

                if ($this->end_date->format('Y-m-d') !== $expectedEndDate->format('Y-m-d')) {
                    $context->buildViolation('The budget period must be exactly 12 months long.')
                        ->atPath('end_date')
                        ->addViolation();
                }
            }
        }
    }


}

