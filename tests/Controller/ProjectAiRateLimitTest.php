<?php

namespace App\Tests\Controller;

use App\Controller\Project\ProjectAiController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ProjectAiRateLimitTest extends TestCase
{
    public function testTenCallsPassThenTheEleventhIsBlockedPerUser(): void
    {
        $cache = new ArrayAdapter();
        $method = new \ReflectionMethod(ProjectAiController::class, 'callsExhausted');
        $controller = (new \ReflectionClass(ProjectAiController::class))->newInstanceWithoutConstructor();

        for ($i = 1; $i <= 10; ++$i) {
            $this->assertFalse($method->invoke($controller, $cache, 7), "call $i");
        }
        $this->assertTrue($method->invoke($controller, $cache, 7), 'call 11');
        $this->assertFalse($method->invoke($controller, $cache, 8), 'another user is not affected');
    }
}
