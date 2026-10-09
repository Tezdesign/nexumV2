<?php

namespace App\Tests\Service\Chat;

use App\Service\Chat\PublicUrlFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublicUrlFetcherTest extends TestCase
{
    /** @dataProvider privateUrls */
    public function testPrivateAndLoopbackHostsAreRefusedBeforeAnythingIsFetched(string $url): void
    {
        $fetcher = new PublicUrlFetcher(HttpClient::create());

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessageMatches('/blocked/');
        $fetcher->fetch($url, [], 1000, 3.0);
    }

    /** @return iterable<string, array{string}> */
    public static function privateUrls(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1:8094/'];
        yield 'short loopback' => ['http://127.1/'];
        yield 'ipv6 loopback' => ['http://[::1]/'];
        yield 'private range' => ['http://10.0.0.5/'];
        yield 'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'host name that resolves to loopback' => ['http://localhost/'];
    }

    public function testBodyIsCutAtTheLimitAndFlagged(): void
    {
        $http = new MockHttpClient(new MockResponse(str_repeat('a', 5000), ['response_headers' => ['content-type: text/html']]));

        $result = (new PublicUrlFetcher($http))->fetch('https://93.184.216.34/page', [], 1000);

        $this->assertSame(1000, strlen($result['body']));
        $this->assertTrue($result['truncated']);
        $this->assertSame('text/html', $result['contentType']);
    }

    public function testSmallBodyIsReturnedWhole(): void
    {
        $http = new MockHttpClient(new MockResponse('<title>Hi</title>', ['response_headers' => ['content-type: text/html; charset=utf-8']]));

        $result = (new PublicUrlFetcher($http))->fetch('https://93.184.216.34/', [], 1000);

        $this->assertSame('<title>Hi</title>', $result['body']);
        $this->assertFalse($result['truncated']);
        $this->assertSame('text/html', $result['contentType']);
        $this->assertSame(200, $result['status']);
    }
}
