<?php

namespace App\Tests\Controller;

use App\Controller\Admin\UserManagementController;
use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AdminLockoutTest extends TestCase
{
    private function reason(Utilisateur $target, string $role, string $status, int $currentUserId, int $otherAdmins): ?string
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn($currentUserId);
        $repo = $this->createMock(UtilisateurRepository::class);
        $repo->method('countOtherActiveAdmins')->willReturn($otherAdmins);
        $controller = new UserManagementController($auth, $repo, $this->createMock(EntityManagerInterface::class), $this->createMock(ValidatorInterface::class));

        return (new \ReflectionMethod($controller, 'lockoutReason'))->invoke($controller, $target, $role, $status);
    }

    private function user(int $id, string $role, string $status): Utilisateur
    {
        $user = (new Utilisateur())->setRole($role)->setStatut($status);
        (new \ReflectionProperty(Utilisateur::class, 'id'))->setValue($user, $id);

        return $user;
    }

    public function testAdminCannotDemoteThemselves(): void
    {
        $this->assertNotNull($this->reason($this->user(1, 'admin', 'active'), 'employee', 'active', 1, 3));
    }

    public function testAdminCannotDeactivateOrDeleteThemselves(): void
    {
        $this->assertNotNull($this->reason($this->user(1, 'admin', 'active'), 'admin', 'pending', 1, 3));
        $this->assertNotNull($this->reason($this->user(1, 'admin', 'active'), '', 'deleted', 1, 3));
    }

    public function testLastActiveAdminCannotBeDemotedOrDeleted(): void
    {
        $this->assertNotNull($this->reason($this->user(2, 'admin', 'active'), 'employee', 'active', 1, 0));
        $this->assertNotNull($this->reason($this->user(2, 'administrator', 'actif'), '', 'deleted', 1, 0));
    }

    public function testAnotherAdminCanBeRemovedWhenOthersRemain(): void
    {
        $this->assertNull($this->reason($this->user(2, 'admin', 'active'), 'employee', 'active', 1, 1));
        $this->assertNull($this->reason($this->user(2, 'admin', 'active'), '', 'deleted', 1, 1));
    }

    public function testOrdinaryUsersAndSelfEditsThatKeepAdminAreFree(): void
    {
        $this->assertNull($this->reason($this->user(3, 'employee', 'active'), 'manager', 'pending', 1, 0));
        $this->assertNull($this->reason($this->user(1, 'admin', 'active'), 'admin', 'active', 1, 0));
    }
}
