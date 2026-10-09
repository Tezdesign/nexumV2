<?php

namespace App\Tests\Controller;

use App\Controller\ResourcesManagement\ClientResourceController;
use App\Repository\Projects\ProjectAssignmentRepository;
use App\Repository\Projects\ProjectRepository;
use App\Service\AuthService;
use PHPUnit\Framework\TestCase;

final class ResourceRequestProjectsTest extends TestCase
{
    public function testRequestableProjectsAreTheUsersOwnAndMemberProjectsWithoutDuplicates(): void
    {
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('getProjectIdsForUser')->with(5)->willReturn([1, 2]);
        $assignments = $this->createMock(ProjectAssignmentRepository::class);
        $assignments->method('getProjectIdsByUserId')->with(5)->willReturn([2, 3]);

        $ids = (new \ReflectionMethod(ClientResourceController::class, 'requestableProjectIds'))->invoke(
            new ClientResourceController($this->createMock(AuthService::class)),
            5,
            $projects,
            $assignments,
        );

        $this->assertSame([1, 2, 3], $ids);
        $this->assertNotContains(99, $ids, 'a project the user does not belong to is not requestable');
    }
}
