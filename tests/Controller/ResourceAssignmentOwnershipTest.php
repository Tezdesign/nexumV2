<?php

namespace App\Tests\Controller;

use App\Controller\ResourcesManagement\ClientResourceController;
use App\Entity\ResourcesManagement\ResourceAssignment;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AuthService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ResourceAssignmentOwnershipTest extends TestCase
{
    private function check(?int $currentUserId, bool $isAdmin, int $ownerId): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn($currentUserId);
        $auth->method('isAdmin')->willReturn($isAdmin);

        $owner = $this->createMock(Utilisateur::class);
        $owner->method('getId')->willReturn($ownerId);
        $assignment = (new ResourceAssignment())->setUtilisateur($owner);

        $method = new \ReflectionMethod(ClientResourceController::class, 'denyUnlessOwnerOrAdmin');
        $method->invoke(new ClientResourceController($auth), $assignment);
    }

    public function testOwnerMayChangeTheirAssignment(): void
    {
        $this->check(5, false, 5);
        $this->addToAssertionCount(1);
    }

    public function testAdminMayChangeAnyAssignment(): void
    {
        $this->check(1, true, 5);
        $this->addToAssertionCount(1);
    }

    public function testAnotherUserIsRefused(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->check(6, false, 5);
    }

    public function testVisitorIsRefused(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->check(null, false, 5);
    }
}
