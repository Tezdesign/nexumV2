<?php

namespace App\Tests\Controller;

use App\Controller\Trait\ProfilePhotoTrait;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ProfileAndLeftoverRoutesTest extends TestCase
{
    /** @param array<string, mixed>|null $sessionUser */
    private function call(string $method, string $uri, ?array $sessionUser = null): Response
    {
        $kernel = new Kernel('test', true);
        $request = Request::create($uri, $method);
        $request->headers->set('Accept', 'text/html');
        $request->setSession(new Session(new MockArraySessionStorage()));
        if ($sessionUser !== null) {
            $request->getSession()->set('user', $sessionUser);
        }

        try {
            return $kernel->handle($request, 1, true);
        } finally {
            $kernel->shutdown();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function removedRoutes(): iterable
    {
        yield 'test api' => ['/test-api'];
        yield 'training' => ['/apps-training'];
        yield 'task details' => ['/apps-task-details'];
        yield 'resources' => ['/apps-resources-management'];
    }

    /** @dataProvider removedRoutes */
    public function testLeftoverRoutesAreGone(string $uri): void
    {
        $this->assertSame(404, $this->call('GET', $uri)->getStatusCode(), $uri);
        $this->assertSame(404, $this->call('GET', $uri, ['id' => 1, 'role' => 'admin'])->getStatusCode(), $uri);
    }

    public function testProfilePostWithoutTokenIsRefused(): void
    {
        $this->assertSame(403, $this->call('POST', '/account', ['id' => 1, 'role' => 'employee'])->getStatusCode());
    }

    private function upload(string $content, string $name): ?string
    {
        $holder = new class extends AbstractController {
            use ProfilePhotoTrait;

            public function read(mixed $file): ?string
            {
                return $this->uploadedPhoto($file);
            }

            protected function addFlash(string $type, mixed $message): void
            {
            }
        };
        $path = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($path, $content);
        $result = $holder->read(new UploadedFile($path, $name, null, null, true));
        @unlink($path);

        return $result;
    }

    public function testRealPngIsAccepted(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $this->assertSame($png, $this->upload($png, 'me.png'));
    }

    public function testHtmlAndSvgAreRejectedWhateverTheName(): void
    {
        $this->assertNull($this->upload('<html><script>alert(1)</script></html>', 'me.png'));
        $this->assertNull($this->upload('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'me.svg'));
    }

    public function testOversizedFileIsRejected(): void
    {
        $this->assertNull($this->upload("\x89PNG\r\n\x1a\n" . str_repeat('a', 2 * 1024 * 1024), 'big.png'));
    }
}
