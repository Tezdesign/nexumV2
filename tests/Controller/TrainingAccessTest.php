<?php

namespace App\Tests\Controller;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/** Goes through the real kernel and routing, so it proves the attributes are really on the routes. */
final class TrainingAccessTest extends TestCase
{
    /** @param array<string, mixed>|null $sessionUser */
    private function call(string $method, string $uri, ?array $sessionUser = null): Response
    {
        $kernel = new Kernel('test', true);
        $request = Request::create($uri, $method);
        $request->headers->set('Accept', 'text/html');
        $session = new Session(new MockArraySessionStorage());
        if ($sessionUser !== null) {
            $session->set('user', $sessionUser);
        }
        $request->setSession($session);

        try {
            return $kernel->handle($request, 1, true);
        } finally {
            $kernel->shutdown();
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function adminRoutes(): iterable
    {
        yield 'new formation' => ['GET', '/formation/new'];
        yield 'edit formation' => ['GET', '/formation/1/edit'];
        yield 'delete formation' => ['POST', '/formation/1'];
        yield 'admin list' => ['GET', '/formation/admin'];
        yield 'admin quiz page' => ['GET', '/formation/1/admin/form'];
        yield 'export' => ['GET', '/formation/admin/export/xls'];
        yield 'training stats' => ['GET', '/admin/training-stats'];
        yield 'chart' => ['GET', '/admin/chart'];
        yield 'quiz edit' => ['POST', '/quiz/1/edit'];
        yield 'quiz delete' => ['POST', '/quiz/1/delete'];
        yield 'quiz ai upload' => ['GET', '/quiz/ai/upload'];
        yield 'quiz ai save' => ['POST', '/quiz/ai/save'];
        yield 'generate image' => ['POST', '/quiz/generate-image'];
    }

    /** @dataProvider adminRoutes */
    public function testVisitorIsSentToTheWelcomePage(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $this->assertSame(302, $response->getStatusCode(), $uri);
        $this->assertSame('/welcome', $response->headers->get('Location'));
    }

    /** @dataProvider adminRoutes */
    public function testLoggedInEmployeeIsForbidden(string $method, string $uri): void
    {
        $response = $this->call($method, $uri, ['id' => 5, 'role' => 'employee']);

        $this->assertSame(403, $response->getStatusCode(), $uri);
    }

    /** @return iterable<string, array{string, string}> */
    public static function userRoutes(): iterable
    {
        yield 'results' => ['GET', '/formation/resultats/formations/quiz'];
        yield 'progress' => ['POST', '/formation/progress/update'];
        yield 'quiz submit' => ['POST', '/formation/quiz/submit'];
        yield 'translate' => ['GET', '/formation/translate/1'];
        yield 'rate' => ['POST', '/formation/1/rate'];
    }

    /** @dataProvider userRoutes */
    public function testUserRoutesNeedALogin(string $method, string $uri): void
    {
        $this->assertSame(302, $this->call($method, $uri)->getStatusCode(), $uri);
    }

    public function testAdminGetsPastTheGuardOnTheCreateForm(): void
    {
        $response = $this->call('GET', '/formation/new', ['id' => 1, 'role' => 'admin']);

        $this->assertNotContains($response->getStatusCode(), [302, 401, 403]);
    }

    public function testVideosDirectoryParameterExistsSoEditingAFormationNoLongerCrashes(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $this->assertStringEndsWith('/public/uploads/videos', (string) $kernel->getContainer()->getParameter('videos_directory'));
        $kernel->shutdown();
    }
}
