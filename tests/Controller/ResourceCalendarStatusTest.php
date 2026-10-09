<?php

namespace App\Tests\Controller;

use App\Controller\ResourcesManagement\ResourceController;
use App\Entity\ResourcesManagement\ResourceAssignment;
use PHPUnit\Framework\TestCase;

final class ResourceCalendarStatusTest extends TestCase
{
    private function statusFor(string $returnDate, bool $returned = false): string
    {
        $assignment = (new ResourceAssignment())
            ->setReturnDate(new \DateTime($returnDate))
            ->setReturned($returned);

        return (new \ReflectionMethod(ResourceController::class, 'returnStatus'))->invoke(
            (new \ReflectionClass(ResourceController::class))->newInstanceWithoutConstructor(),
            $assignment,
            new \DateTimeImmutable('today'),
        );
    }

    public function testDueTodayIsNotOverdue(): void
    {
        $this->assertSame('DUE TODAY', $this->statusFor('today'));
    }

    public function testYesterdayIsOverdue(): void
    {
        $this->assertSame('OVERDUE', $this->statusFor('yesterday'));
    }

    public function testTomorrowIsStillActive(): void
    {
        $this->assertSame('ACTIVE', $this->statusFor('tomorrow'));
    }

    public function testReturnedItemsAreNeverOverdue(): void
    {
        $this->assertSame('RETURNED', $this->statusFor('-10 days', returned: true));
    }

    public function testNoReturnDateIsActive(): void
    {
        $assignment = new ResourceAssignment();
        $status = (new \ReflectionMethod(ResourceController::class, 'returnStatus'))->invoke(
            (new \ReflectionClass(ResourceController::class))->newInstanceWithoutConstructor(),
            $assignment,
            new \DateTimeImmutable('today'),
        );

        $this->assertSame('ACTIVE', $status);
    }
}
