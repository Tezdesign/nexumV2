<?php

namespace App\Tests\Service\Project;

use App\Service\Project\AI\OpenVinoRapportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenVinoRapportServiceTest extends TestCase
{
    public function testEngineReportedOfflineWhenTheConnectionFails(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'Connection refused']));

        $status = (new OpenVinoRapportService($http, new ArrayAdapter()))->getStatus();

        $this->assertFalse($status['available']);
        $this->assertStringContainsString('hors ligne', $status['message']);
    }

    /** @dataProvider answers */
    public function testOnlyTheEnginesOwnAnswerCountsAsOnline(int $code, bool $online): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => $code]));

        $this->assertSame($online, (new OpenVinoRapportService($http, new ArrayAdapter()))->getStatus()['available']);
    }

    /** @return iterable<string, array{int, bool}> */
    public static function answers(): iterable
    {
        yield 'route exists, GET not allowed' => [405, true];
        yield 'success' => [200, true];
        yield 'another program (AirPlay)' => [403, false];
        yield 'unknown route' => [404, false];
        yield 'engine crashed' => [500, false];
    }

    public function testStatusIsCachedSoAnOfflineEngineDoesNotSlowEveryPageLoad(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('', ['error' => 'down']);
        });
        $service = new OpenVinoRapportService($http, new ArrayAdapter());

        $service->getStatus();
        $service->getStatus();

        $this->assertSame(1, $calls);
    }
}
