<?php

namespace App\Tests\Controller;

use App\Controller\FormationController;
use App\Entity\Formation;
use App\Service\TranslatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class TrainingTranslationTest extends TestCase
{
    private int $calls = 0;

    private function translator(): TranslatorService
    {
        $this->calls = 0;
        $http = new MockHttpClient(function (): MockResponse {
            ++$this->calls;

            return new MockResponse(json_encode(['responseStatus' => 200, 'responseData' => ['translatedText' => 'Learn PHP']]));
        });

        return new TranslatorService($http, new ArrayAdapter());
    }

    public function testTheDetailPageNoLongerCallsTheTranslationService(): void
    {
        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => (string) $p->getType(),
            (new \ReflectionMethod(FormationController::class, 'show'))->getParameters()
        );

        $this->assertNotContains(TranslatorService::class, $parameters);
    }

    public function testChosenLanguageIsTranslatedOnDemand(): void
    {
        $formation = (new Formation())->setTitre('PHP')->setDescription('Apprendre PHP');

        $response = (new FormationController())->translate($formation, Request::create('/formation/translate/1', 'GET', ['lang' => 'en']), $this->translator());

        $this->assertSame('Learn PHP', json_decode((string) $response->getContent(), true)['translated']);
        $this->assertSame(1, $this->calls);
    }

    /** @dataProvider badLanguages */
    public function testUnknownLanguagesAreRefusedBeforeAnyCall(string $lang): void
    {
        $formation = (new Formation())->setTitre('PHP')->setDescription('Apprendre PHP');

        $response = (new FormationController())->translate($formation, Request::create('/x', 'GET', ['lang' => $lang]), $this->translator());

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, $this->calls);
    }

    /** @return iterable<string, array{string}> */
    public static function badLanguages(): iterable
    {
        yield 'unknown' => ['xx'];
        yield 'injection' => ['en&de=1'];
        yield 'empty' => [''];
    }
}
