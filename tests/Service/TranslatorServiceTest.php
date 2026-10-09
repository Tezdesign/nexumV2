<?php

namespace App\Tests\Service;

use App\Service\TranslatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TranslatorServiceTest extends TestCase
{
    /** @return array{0: TranslatorService, 1: \Closure(): int} service and a counter of HTTP calls */
    private function service(callable $answer): array
    {
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($answer, &$calls): MockResponse {
            ++$calls;

            return $answer($options['query'] ?? []);
        });

        return [new TranslatorService($http, new ArrayAdapter()), static function () use (&$calls): int {
            return $calls;
        }];
    }

    private static function ok(string $text): MockResponse
    {
        return new MockResponse(json_encode(['responseStatus' => 200, 'responseData' => ['translatedText' => $text]]));
    }

    public function testSecondRequestForTheSameTextCostsNoHttpCall(): void
    {
        [$service, $calls] = $this->service(fn (array $q) => self::ok('Hello'));

        $this->assertSame('Hello', $service->translate('Bonjour', 'fr', 'en'));
        $this->assertSame('Hello', $service->translate('Bonjour', 'fr', 'en'));
        $this->assertSame(1, $calls());
    }

    public function testEachLanguageIsCachedSeparately(): void
    {
        [$service, $calls] = $this->service(fn (array $q) => self::ok('['.$q['langpair'].']'));

        $this->assertSame('[fr|en]', $service->translate('Bonjour', 'fr', 'en'));
        $this->assertSame('[fr|de]', $service->translate('Bonjour', 'fr', 'de'));
        $this->assertSame(2, $calls());
    }

    public function testAFailureReturnsTheOriginalTextAndIsNotCached(): void
    {
        $fail = true;
        [$service, $calls] = $this->service(function () use (&$fail) {
            return $fail ? new MockResponse('', ['http_code' => 500]) : self::ok('Hello');
        });

        $this->assertSame('Bonjour', $service->translate('Bonjour', 'fr', 'en'));

        $fail = false;
        $this->assertSame('Hello', $service->translate('Bonjour', 'fr', 'en'), 'the next request tries again');
        $this->assertSame(2, $calls());
    }

    public function testQuotaWarningFromTheApiIsNotTreatedAsATranslation(): void
    {
        [$service] = $this->service(fn () => new MockResponse(json_encode([
            'responseStatus' => 429,
            'responseData' => ['translatedText' => 'MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY'],
        ])));

        $this->assertSame('Bonjour', $service->translate('Bonjour', 'fr', 'en'));
    }

    public function testNothingIsSentWhenThereIsNothingToTranslate(): void
    {
        [$service, $calls] = $this->service(fn () => self::ok('x'));

        $this->assertSame('', $service->translate('   ', 'fr', 'en'));
        $this->assertSame('Bonjour', $service->translate('Bonjour', 'fr', 'fr'));
        $this->assertSame(0, $calls());
    }

    public function testLongTextIsCutIntoPiecesAtWordBoundariesWithoutBreakingAccents(): void
    {
        $text = str_repeat('Éducation à la sécurité informatique. ', 30); // about 1,100 characters, many accents
        $chunks = TranslatorService::splitText($text, 400);

        $this->assertGreaterThan(2, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(400, mb_strlen($chunk));
            $this->assertTrue(mb_check_encoding($chunk, 'UTF-8'), 'no multi byte letter is split');
        }
        $this->assertSame($text, implode('', $chunks), 'nothing is lost or repeated');
    }

    public function testAWordLongerThanThePieceIsStillSplit(): void
    {
        $chunks = TranslatorService::splitText(str_repeat('é', 950), 400);

        $this->assertSame([400, 400, 150], array_map('mb_strlen', $chunks));
    }

    public function testEveryPieceOfALongTextIsRequested(): void
    {
        [$service, $calls] = $this->service(fn (array $q) => self::ok(strtoupper(substr($q['q'], 0, 1))));

        $service->translate(str_repeat('mot ', 300), 'fr', 'en'); // 1,200 characters

        $this->assertSame(3, $calls());
    }

    public function testOnlyTheLanguagesOfThePageAreSupported(): void
    {
        foreach (['fr', 'en', 'ar', 'es', 'de'] as $language) {
            $this->assertTrue(TranslatorService::isSupported($language));
        }
        $this->assertFalse(TranslatorService::isSupported('xx|en&de=1'));
        $this->assertFalse(TranslatorService::isSupported(''));
    }
}
