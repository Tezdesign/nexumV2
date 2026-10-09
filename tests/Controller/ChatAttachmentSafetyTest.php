<?php

namespace App\Tests\Controller;

use App\Controller\chat\MessageAttachmentController;
use App\Service\AuthService;
use App\Service\Chat\PublicUrlFetcher;
use App\Service\Chat\UserAvatarUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ChatAttachmentSafetyTest extends TestCase
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private function controller(): MessageAttachmentController
    {
        return new MessageAttachmentController($this->createMock(AuthService::class), new UserAvatarUrl($this->createMock(UrlGeneratorInterface::class)));
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod(MessageAttachmentController::class, $method))->invoke($this->controller(), ...$args);
    }

    /** @return array{0:string,1:string,2:string,3:int} */
    private function download(string $body, int $status = 200, string $url = 'https://93.184.216.34/anim/cat.gif'): array
    {
        $fetcher = new PublicUrlFetcher(new MockHttpClient(new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type: image/gif']])));

        return $this->invoke('downloadRemoteGif', $url, $fetcher);
    }

    /** @dataProvider inlineTypes */
    public function testOnlyPlainPicturesAndMediaAreShownInline(string $mime, bool $inline): void
    {
        $this->assertSame($inline, $this->invoke('isInlineMediaMimeType', $mime));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function inlineTypes(): iterable
    {
        yield 'png' => ['image/png', true];
        yield 'gif' => ['image/gif', true];
        yield 'video' => ['video/mp4', true];
        yield 'audio' => ['audio/mpeg', true];
        yield 'svg can carry script' => ['image/svg+xml', false];
        yield 'html' => ['text/html', false];
        yield 'pdf' => ['application/pdf', false];
    }

    public function testRealGifIsAccepted(): void
    {
        [$bytes, $mime, $name, $size] = $this->download((string) base64_decode(self::GIF));

        $this->assertSame('image/gif', $mime);
        $this->assertSame('cat.gif', $name);
        $this->assertSame(strlen($bytes), $size);
    }

    public function testSvgPretendingToBeAGifIsRefusedWhateverTheServerSays(): void
    {
        $this->expectExceptionMessage('Unsupported GIF format.');
        $this->download('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    }

    public function testHtmlPretendingToBeAGifIsRefused(): void
    {
        $this->expectExceptionMessage('Unsupported GIF format.');
        $this->download('<html><script>alert(1)</script></html>');
    }

    public function testHugeDownloadIsRefusedInsteadOfLoadedIntoMemory(): void
    {
        $this->expectExceptionMessage('GIF is too big');
        $this->download(str_repeat('G', 16 * 1024 * 1024));
    }

    public function testServerErrorIsReportedAsDownloadFailure(): void
    {
        $this->expectExceptionMessage('Could not download GIF.');
        $this->download('nope', 500);
    }

    public function testPrivateSourceNeverGetsFetched(): void
    {
        $this->expectExceptionMessage('Could not download GIF.');
        $this->download((string) base64_decode(self::GIF), 200, 'http://10.0.0.5/cat.gif');
    }

    public function testGifUrlShapeCheck(): void
    {
        $this->assertTrue($this->invoke('isAllowedGifSourceUrl', 'https://93.184.216.34/a.gif'));
        $this->assertFalse($this->invoke('isAllowedGifSourceUrl', 'ftp://93.184.216.34/a.gif'));
        $this->assertFalse($this->invoke('isAllowedGifSourceUrl', 'https://user:pass@93.184.216.34/a.gif'));
        $this->assertFalse($this->invoke('isAllowedGifSourceUrl', 'not a url'));
    }
}
