<?php

namespace App\Tests\Controller;

use App\Controller\Trait\ReclamationAttachmentTrait;
use App\Entity\UserHandling\Reclamation;
use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class AdminAccessTest extends TestCase
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

    /** @return iterable<string, array{string, string}> */
    public static function adminRoutes(): iterable
    {
        yield 'reclamation history' => ['GET', '/admin/reclamations/1/history'];
        yield 'users' => ['GET', '/admin/users'];
        yield 'reclamations' => ['GET', '/admin/reclamations'];
        yield 'attachment' => ['GET', '/admin/reclamations/1/fichier'];
        yield 'audit' => ['GET', '/admin/audit'];
        yield 'delete user' => ['POST', '/admin/users/1/delete'];
    }

    /** @dataProvider adminRoutes */
    public function testVisitorIsSentToTheWelcomePage(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $this->assertSame(302, $response->getStatusCode(), $uri);
        $this->assertSame('/welcome', $response->headers->get('Location'));
    }

    /** @dataProvider adminRoutes */
    public function testEmployeeIsForbidden(string $method, string $uri): void
    {
        $this->assertSame(403, $this->call($method, $uri, ['id' => 5, 'role' => 'employee'])->getStatusCode(), $uri);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function postRoutes(): iterable
    {
        yield 'delete user' => ['POST', '/admin/users/1/delete', 'admin'];
        yield 'activate user' => ['POST', '/admin/users/1/activate', 'admin'];
        yield 'new user' => ['POST', '/admin/users/new', 'admin'];
        yield 'delete reclamation' => ['POST', '/admin/reclamations/1/delete', 'admin'];
        yield 'new reclamation' => ['POST', '/admin/reclamations/new', 'admin'];
        yield 'user creates reclamation' => ['POST', '/mes-reclamations/nouvelle', 'employee'];
        yield 'user deletes reclamation' => ['POST', '/mes-reclamations/1/supprimer', 'employee'];
    }

    /** @dataProvider postRoutes */
    public function testPostWithoutCsrfTokenIsRefused(string $method, string $uri, string $role): void
    {
        $this->assertSame(403, $this->call($method, $uri, ['id' => 1, 'role' => $role])->getStatusCode(), $uri);
    }

    private function holder(): object
    {
        return new class extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController {
            use ReclamationAttachmentTrait;

            public function serve(Reclamation $rec): Response
            {
                return $this->attachmentResponse($rec);
            }

            public function upload(Request $request): ?string
            {
                return $this->uploadedAttachment($request);
            }

            protected function addFlash(string $type, mixed $message): void
            {
            }
        };
    }

    public function testHtmlAttachmentIsDownloadedNeverShown(): void
    {
        $rec = (new Reclamation())->setFichier('<html><script>alert(1)</script></html>');
        $response = $this->holder()->serve($rec);

        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testPdfAttachmentStaysInline(): void
    {
        $rec = (new Reclamation())->setFichier("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $response = $this->holder()->serve($rec);

        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    private function upload(string $content, string $name): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'rec');
        file_put_contents($path, $content);
        $request = new Request();
        $request->files->set('fichier', new UploadedFile($path, $name, null, null, true));
        $result = $this->holder()->upload($request);
        @unlink($path);

        return $result;
    }

    public function testUploadRejectsHtmlEvenWithAnImageName(): void
    {
        $this->assertNull($this->upload('<html><script>alert(1)</script></html>', 'photo.png'));
    }

    public function testUploadRejectsSvg(): void
    {
        $this->assertNull($this->upload('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'a.svg'));
    }

    public function testUploadRejectsFilesOverFiveMegabytes(): void
    {
        $this->assertNull($this->upload(str_repeat('a', 5 * 1024 * 1024 + 1), 'big.txt'));
    }

    public function testUploadAcceptsPlainText(): void
    {
        $this->assertSame('hello', $this->upload('hello', 'note.txt'));
    }
}
