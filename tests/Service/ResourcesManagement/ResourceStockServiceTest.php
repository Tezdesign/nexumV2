<?php

namespace App\Tests\Service\ResourcesManagement;

use App\Entity\ResourcesManagement\Resource;
use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\UserHandling\Utilisateur;
use App\Service\ResourcesManagement\ResourceStockService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class ResourceStockServiceTest extends TestCase
{
    private EntityManager $em;
    private ResourceStockService $stock;
    private Resource $laptop;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 3).'/src/Entity'], true);
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(Utilisateur::class),
            $this->em->getClassMetadata(Resource::class),
            $this->em->getClassMetadata(ResourceAssignment::class),
        ]);

        $registry = $this->registry();
        $this->stock = new ResourceStockService(
            $this->em,
            new \App\Repository\ResourcesManagement\ResourceAssignmentRepository($registry),
            new \App\Repository\ResourcesManagement\ResourceRepository($registry),
        );

        $this->laptop = (new Resource())
            ->setResourceCode('LAP-001')->setResourceName('Laptop')->setResourceType('PHYSICAL')
            ->setUnitCost('10.00')->setTotalQuantity(10)->setAvailableQuantity(10);
        $this->em->persist($this->laptop);
        $this->em->flush();
    }

    private function registry(): \Doctrine\Persistence\ManagerRegistry
    {
        $registry = $this->createMock(\Doctrine\Persistence\ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        return $registry;
    }

    private function assignment(int $quantity, string $status, bool $returned = false): ResourceAssignment
    {
        $assignment = (new ResourceAssignment())
            ->setResourceId((int) $this->laptop->getResourceId())->setProjectCode('1')
            ->setQuantity($quantity)->setAssignmentDate(new \DateTime())->setStatus($status)
            ->setPenaltyDaysApplied(0)->setBonusApplied(false)->setReturned($returned);
        $this->em->persist($assignment);
        $this->em->flush();

        return $assignment;
    }

    private function available(): int
    {
        $this->stock->recalculate($this->laptop);

        return (int) $this->laptop->getAvailableQuantity();
    }

    public function testOnlyAcceptedUnreturnedAssignmentsTakeStock(): void
    {
        $this->assignment(3, 'ACCEPTED');
        $this->assignment(2, 'PENDING');
        $this->assignment(4, 'DECLINED');
        $this->assignment(1, 'ACCEPTED', returned: true);

        $this->assertSame(7, $this->available());
    }

    public function testReturningAnItemPutsItBackInStock(): void
    {
        $loan = $this->assignment(4, 'ACCEPTED');
        $this->assertSame(6, $this->available());

        $loan->setReturned(true);
        $this->em->flush();

        $this->assertSame(10, $this->available());
    }

    public function testDecliningOrDeletingAPendingRequestDoesNotInventStock(): void
    {
        $pending = $this->assignment(3, 'PENDING');
        $this->assertSame(10, $this->available());

        $pending->setStatus('DECLINED');
        $this->em->flush();
        $this->assertSame(10, $this->available());

        $this->em->remove($pending);
        $this->em->flush();
        $this->assertSame(10, $this->available());
    }

    public function testRecalculatingTwiceGivesTheSameNumber(): void
    {
        $this->assignment(5, 'ACCEPTED');

        $this->assertSame(5, $this->available());
        $this->assertSame(5, $this->available());
    }

    public function testEditingTheTotalKeepsLentUnitsOut(): void
    {
        $this->assignment(4, 'ACCEPTED');

        $this->laptop->setTotalQuantity(12);

        $this->assertSame(8, $this->available());
    }

    public function testRecalculateAllFixesDriftedValues(): void
    {
        $this->assignment(2, 'ACCEPTED');
        $this->laptop->setAvailableQuantity(99);
        $this->em->flush();

        $this->assertSame(1, $this->stock->recalculateAll());
        $this->assertSame(8, $this->laptop->getAvailableQuantity());
    }
}
