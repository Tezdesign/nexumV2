<?php

namespace App\Tests\Entity\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\FinancialAnalysis\Transaction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TransactionTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testGettersAndSetters(): void
    {
        $transaction = new Transaction();
        $projectBudget = new ProjectBudget();
        $date = new \DateTime('2025-01-01');

        $transaction->setId(1);
        $this->assertSame(1, $transaction->getId());

        $transaction->setReference('TX-123456');
        $this->assertSame('TX-123456', $transaction->getReference());

        $transaction->setCost('100.50');
        $this->assertSame('100.50', $transaction->getCost());

        $transaction->setDateStamp($date);
        $this->assertSame($date, $transaction->getDateStamp());

        $transaction->setExpenseCategory('Equipment');
        $this->assertSame('Equipment', $transaction->getExpenseCategory());
        // Testing both setter forms due to older property names
        $transaction->setExpense_category('Software');
        $this->assertSame('Software', $transaction->getExpense_category());

        $transaction->setProjectBudget($projectBudget);
        $this->assertSame($projectBudget, $transaction->getProjectBudget());

        $transaction->setDescription('Purchase of new software');
        $this->assertSame('Purchase of new software', $transaction->getDescription());
    }

    public function testValidTransaction(): void
    {
        $transaction = new Transaction();
        $transaction->setReference('TX-123456');
        $transaction->setCost('150.00');
        $transaction->setDateStamp(new \DateTime('-1 day'));
        $transaction->setExpenseCategory('Services');
        $transaction->setDescription('Consulting services');

        $errors = $this->validator->validate($transaction);

        $this->assertCount(0, $errors, (string) $errors);
    }

    public function testInvalidReferenceFormat(): void
    {
        $transaction = new Transaction();
        $transaction->setReference('INVALID-REF'); // Should fail regex ^TX-\d{6}$
        $transaction->setCost('150.00');
        $transaction->setDateStamp(new \DateTime('-1 day'));
        $transaction->setExpenseCategory('Services');
        $transaction->setDescription('Valid desc');

        $errors = $this->validator->validate($transaction);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('TX-', (string) $errors);
    }

    public function testInvalidCost(): void
    {
        $transaction = new Transaction();
        $transaction->setReference('TX-654321');
        $transaction->setCost('-50.00'); // Should be positive
        $transaction->setDateStamp(new \DateTime('-1 day'));
        $transaction->setExpenseCategory('Services');
        $transaction->setDescription('Valid desc');

        $errors = $this->validator->validate($transaction);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('greater than 0', (string) $errors);
    }

    public function testFutureDate(): void
    {
        $transaction = new Transaction();
        $transaction->setReference('TX-654321');
        $transaction->setCost('50.00');
        $transaction->setDateStamp(new \DateTime('+2 days')); // Should not be in the future
        $transaction->setExpenseCategory('Services');
        $transaction->setDescription('Valid desc');

        $errors = $this->validator->validate($transaction);
        $this->assertGreaterThan(0, count($errors));
        $this->assertStringContainsString('future', (string) $errors);
    }

    public function testBlankFields(): void
    {
        $transaction = new Transaction();
        // Leaving fields blank to trigger @Assert\NotBlank
        
        $errors = $this->validator->validate($transaction);
        $this->assertGreaterThanOrEqual(4, count($errors)); // Reference, cost, date_stamp, expense_category, description
    }
}
