<?php

namespace App\Tests\Controller;

use App\Controller\FormationController;
use App\Entity\Formation;
use App\Entity\Participer;
use App\Entity\Quiz;
use App\Entity\Rating;
use App\Entity\Resultat;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AuthService;
use App\Service\CertificateService;
use App\Service\QrService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Knp\Component\Pager\Pagination\SlidingPagination;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TrainingProgressAndQuizTest extends TestCase
{
    private EntityManager $em;
    private Utilisateur $ada;
    private Utilisateur $bob;
    private Formation $formation;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);

        // The app's repositories are built from a ManagerRegistry, so give them one that points at this test database.
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (): EntityManager => $this->em);
        $config->setRepositoryFactory(new class($registry) implements RepositoryFactory {
            public function __construct(private readonly ManagerRegistry $registry)
            {
            }

            public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
            {
                $metadata = $entityManager->getClassMetadata($entityName);
                $class = $metadata->customRepositoryClassName;

                return $class !== null ? new $class($this->registry) : new EntityRepository($entityManager, $metadata);
            }
        });

        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($this->em))->createSchema(array_map(
            fn (string $class) => $this->em->getClassMetadata($class),
            [Utilisateur::class, Formation::class, Participer::class, Quiz::class, Resultat::class, Rating::class]
        ));

        $this->ada = $this->user('Ada', 'ada@example.test');
        $this->bob = $this->user('Bob', 'bob@example.test');
        $this->formation = (new Formation())->setTitre('PHP')->setDescription('Learn PHP');
        $this->em->persist($this->formation);
        foreach ([1, 2, 3, 4] as $n) {
            $this->em->persist((new Quiz())->setFormation($this->formation)->setQuestion("Question $n ?")
                ->setR1('a')->setR2('b')->setR3('c')->setCorrect(1)->setType('mcq')->setSource('manual'));
        }
        $this->em->flush();
    }

    private function user(string $name, string $email): Utilisateur
    {
        $user = (new Utilisateur())->setNom($name)->setPrenom($name)->setEmail($email)->setPassword('x')
            ->setRole('employee')->setStatut('active')->setDateInscription(new \DateTime());
        $this->em->persist($user);

        return $user;
    }

    private function participation(Utilisateur $user, string $status, int $progress): Participer
    {
        $participation = (new Participer())->setUser($user)->setFormation($this->formation)
            ->setStatut($status)->setProgression($progress)->setDateInscription(new \DateTime());
        $this->em->persist($participation);
        $this->em->flush();

        return $participation;
    }

    private function auth(Utilisateur $user): AuthService
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn($user->getId());

        return $auth;
    }

    private function controller(): FormationController
    {
        $controller = new FormationController();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function postProgress(Utilisateur $user, int $formationId, int $progress): JsonResponse
    {
        $request = Request::create('/formation/progress/update', 'POST', ['formationId' => $formationId, 'progress' => $progress]);

        return $this->controller()->updateProgress($request, $this->em, $this->auth($user));
    }

    /** @param array<int|string, int|string> $answers */
    private function submit(Utilisateur $user, array $answers, ?CertificateService $certificate = null): JsonResponse
    {
        $request = Request::create('/formation/quiz/submit', 'POST', [], [], [], [], json_encode(['formationId' => $this->formation->getId(), 'answers' => $answers]));
        $qr = $this->createMock(QrService::class);
        $certificate ??= $this->createMock(CertificateService::class);

        return $this->controller()->submitQuiz(
            $request, $this->em, $certificate, $qr, $this->auth($user), new NullLogger()
        );
    }

    /** @return array<int, int> every question answered with the same choice */
    private function allAnswered(int $choice): array
    {
        $answers = [];
        foreach ($this->em->getRepository(Quiz::class)->findAll() as $quiz) {
            $answers[(int) $quiz->getId()] = $choice;
        }

        return $answers;
    }

    private function statusOf(Utilisateur $user): ?string
    {
        $this->em->clear();

        return $this->em->getRepository(Participer::class)->findOneBy(['user' => $user->getId()])?->getStatut();
    }

    // ---------------------------------------------------------------- progress

    public function testProgressMovesOneVideoAtATimeAndOnlyTheThirdUnlocksTheQuiz(): void
    {
        $id = (int) $this->formation->getId();

        $this->assertSame(33, json_decode((string) $this->postProgress($this->ada, $id, 100)->getContent(), true)['progress']);
        $this->assertSame(66, json_decode((string) $this->postProgress($this->ada, $id, 100)->getContent(), true)['progress']);
        $this->assertNotSame('PRET_QUIZ', $this->statusOf($this->ada));

        $this->assertSame(90, json_decode((string) $this->postProgress($this->ada, $id, 100)->getContent(), true)['progress']);
        $this->assertSame('PRET_QUIZ', $this->statusOf($this->ada));
    }

    public function testReportingAnEarlierOrSmallerValueChangesNothing(): void
    {
        $id = (int) $this->formation->getId();
        $this->participation($this->ada, 'PARTICIPER', 66);

        $this->assertSame(66, json_decode((string) $this->postProgress($this->ada, $id, 10)->getContent(), true)['progress']);
        $this->assertSame(66, json_decode((string) $this->postProgress($this->ada, $id, 0)->getContent(), true)['progress']);
    }

    public function testProgressDoesNotReopenAFinishedOrFailedFormation(): void
    {
        $this->participation($this->ada, 'REUSSI', 66);
        $this->postProgress($this->ada, (int) $this->formation->getId(), 100);
        $this->postProgress($this->ada, (int) $this->formation->getId(), 100);

        $this->assertSame('REUSSI', $this->statusOf($this->ada));
    }

    public function testUnknownFormationIsRefusedInsteadOfCrashing(): void
    {
        $this->assertSame(404, $this->postProgress($this->ada, 9999, 33)->getStatusCode());
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM participer'));
    }

    // ---------------------------------------------------------------- quiz

    public function testQuizStaysClosedUntilTheVideosAreDone(): void
    {
        $this->participation($this->ada, 'PARTICIPER', 33);

        $response = $this->submit($this->ada, $this->allAnswered(1));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM resultat'));
    }

    public function testPassingSavesTheResultAndSendsTheCertificateOnce(): void
    {
        $this->participation($this->ada, 'PRET_QUIZ', 90);
        $certificate = $this->createMock(CertificateService::class);
        $file = tempnam(sys_get_temp_dir(), 'cert');
        $certificate->expects($this->once())->method('generate')->willReturn($file);

        $response = $this->submit($this->ada, $this->allAnswered(1), $certificate);

        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(4, $body['score']);
        $this->assertSame(100, (int) $body['percent']);
        $this->assertSame('REUSSI', $this->statusOf($this->ada));
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM resultat'));
    }

    public function testFailingMarksEchecAndNeverTouchesTheCertificate(): void
    {
        $this->participation($this->ada, 'PRET_QUIZ', 90);
        $certificate = $this->createMock(CertificateService::class);
        $certificate->expects($this->never())->method('generate');

        $response = $this->submit($this->ada, $this->allAnswered(3), $certificate);

        $this->assertSame('Quiz non validé.', json_decode((string) $response->getContent(), true)['message']);
        $this->assertSame('ECHEC', $this->statusOf($this->ada));
    }

    public function testThreeScoredAttemptsADayThenTheFourthIsRefused(): void
    {
        $this->participation($this->ada, 'PRET_QUIZ', 90);

        foreach ([1, 2, 3] as $attempt) {
            $this->assertSame(200, $this->submit($this->ada, $this->allAnswered(3))->getStatusCode(), "attempt $attempt");
        }

        $this->assertSame(429, $this->submit($this->ada, $this->allAnswered(1))->getStatusCode());
        $this->assertSame('ECHEC', $this->statusOf($this->ada), 'a refused attempt must not be able to pass');
    }

    public function testResponsesNeverCarryServerDetails(): void
    {
        $this->participation($this->ada, 'PRET_QUIZ', 90);
        $certificate = $this->createMock(CertificateService::class);
        $certificate->method('generate')->willThrowException(new \RuntimeException('Cannot write /var/www/secret/path.pdf'));

        $response = $this->submit($this->ada, $this->allAnswered(1), $certificate);
        $content = (string) $response->getContent();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('debug', $content);
        $this->assertStringNotContainsString('/var/www', $content);
        $this->assertSame('REUSSI', $this->statusOf($this->ada), 'the pass itself is kept so the certificate can be asked again');
    }

    // ---------------------------------------------------------------- list

    public function testListShowsTheCurrentUsersOwnProgressNotAnotherUsers(): void
    {
        $this->participation($this->ada, 'PRET_QUIZ', 90);   // Ada has progress on the formation...
        $request = Request::create('/formation');             // ...Bob has none.

        $page = new SlidingPagination([]);
        $page->setItems([$this->em->getRepository(Formation::class)->find($this->formation->getId())]);
        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->method('paginate')->willReturn($page);

        $controller = $this->controller();
        $container = new Container();
        $container->set('twig', new Environment(new ArrayLoader([
            'formation/index.html.twig' => '{% for id, p in participations %}{{ id }}={{ p ? p.statut : "none" }};{% endfor %}',
        ])));
        $controller->setContainer($container);

        $repository = $this->createMock(\App\Repository\FormationRepository::class);
        $repository->method('createQueryBuilder')->willReturn($this->em->createQueryBuilder()->select('f')->from(Formation::class, 'f'));

        $html = $controller->index($request, $repository, $this->em, $paginator, $this->auth($this->bob))->getContent();

        $this->assertSame($this->formation->getId().'=none;', $html);
    }
}
