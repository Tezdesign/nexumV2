<?php

namespace App\Tests\Controller;

use App\Controller\FormationController;
use App\Controller\RatingController;
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
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TrainingRatingAndCertificateTest extends TestCase
{
    private EntityManager $em;
    private Utilisateur $ada;
    private Utilisateur $bob;
    private Formation $formation;
    private string $project;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
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
        $this->em->flush();

        $this->project = sys_get_temp_dir().'/nexum-train-'.bin2hex(random_bytes(4));
        mkdir($this->project.'/public', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->project.'/var/*/*') as $file) {
            @unlink($file);
        }
    }

    private function user(string $name, string $email): Utilisateur
    {
        $user = (new Utilisateur())->setNom($name)->setPrenom($name)->setEmail($email)->setPassword('x')
            ->setRole('employee')->setStatut('active')->setDateInscription(new \DateTime());
        $this->em->persist($user);

        return $user;
    }

    private function auth(Utilisateur $user, bool $admin = false): AuthService
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('getCurrentUserId')->willReturn($user->getId());
        $auth->method('isAdmin')->willReturn($admin);

        return $auth;
    }

    private function rate(Utilisateur $user, mixed $value, bool $admin = false): \Symfony\Component\HttpFoundation\Response
    {
        $controller = new RatingController();
        $container = new Container();
        $container->set('router', new class implements \Symfony\Component\Routing\Generator\UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/'.$name.'/'.($parameters['id'] ?? '');
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void
            {
            }

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }
        });
        $container->set('request_stack', new \Symfony\Component\HttpFoundation\RequestStack());
        $controller->setContainer($container);

        $request = Request::create('/formation/1/rate', 'POST', ['rating' => $value]);
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
        $container->get('request_stack')->push($request);

        return $controller->rate($this->formation, $request, $this->em, $this->auth($user, $admin));
    }

    private function ratings(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM rating');
    }

    // ---------------------------------------------------------------- ratings

    public function testRatingAgainReplacesTheNoteInsteadOfAddingAnother(): void
    {
        $this->rate($this->ada, 5);
        $this->rate($this->ada, 5);
        $this->rate($this->ada, 2);

        $this->assertSame(1, $this->ratings());
        $this->assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT value FROM rating'));
    }

    public function testEachUserHasTheirOwnNoteAndTheAverageUsesBoth(): void
    {
        $this->rate($this->ada, 5);
        $this->rate($this->bob, 3);
        $this->em->clear();

        $this->assertSame(2, $this->ratings());
        $this->assertSame(4.0, $this->em->getRepository(Formation::class)->find($this->formation->getId())->getAverageRating());
    }

    /** @dataProvider badNotes */
    public function testNotesOutsideOneToFiveAreRefused(mixed $value): void
    {
        $this->rate($this->ada, $value);

        $this->assertSame(0, $this->ratings());
    }

    /** @return iterable<string, array{mixed}> */
    public static function badNotes(): iterable
    {
        yield 'zero' => [0];
        yield 'six' => [6];
        yield 'negative' => [-1];
        yield 'text' => ['abc'];
        yield 'empty' => [''];
    }

    public function testUsersAndAdminsAreSentBackToTheirOwnPage(): void
    {
        $this->assertStringStartsWith('/app_formation_show/', (string) $this->rate($this->ada, 4)->headers->get('Location'));
        $this->assertStringStartsWith('/app_formation_show_admin/', (string) $this->rate($this->bob, 4, true)->headers->get('Location'));
    }

    public function testTheDatabaseItselfRefusesASecondNoteFromTheSameUser(): void
    {
        $this->rate($this->ada, 4);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->em->persist((new Rating())->setUser($this->ada)->setFormation($this->formation)->setValue(1));
        $this->em->flush();
    }

    // ---------------------------------------------------------------- certificate download

    private function passed(Utilisateur $user, string $status = 'REUSSI'): void
    {
        $this->em->persist((new Participer())->setUser($user)->setFormation($this->formation)
            ->setStatut($status)->setProgression(90)->setDateInscription(new \DateTime()));
        $this->em->flush();
    }

    private function download(Utilisateur $user): \Symfony\Component\HttpFoundation\Response
    {
        $controller = new FormationController();
        $controller->setContainer(new Container());

        return $controller->certificate(
            $this->formation, $this->em, new CertificateService($this->project), new QrService($this->project),
            $this->auth($user), new NullLogger()
        );
    }

    public function testThePersonWhoPassedDownloadsTheirOwnCertificateFromAPrivateFolder(): void
    {
        $this->passed($this->ada);

        $response = $this->download($this->ada);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith($this->project.'/var/certificates/', $response->getFile()->getPathname());
        $this->assertSame([], glob($this->project.'/var/qr/*'), 'the QR image does not stay on disk');
        $this->assertSame([], glob($this->project.'/public/*'));
    }

    public function testSomeoneWhoDidNotPassGetsNothing(): void
    {
        $this->passed($this->ada, 'PRET_QUIZ');

        $this->expectException(NotFoundHttpException::class);
        $this->download($this->ada);
    }

    public function testAnotherUserCannotDownloadSomeoneElsesCertificate(): void
    {
        $this->passed($this->ada);   // Ada passed, Bob never took part.

        $this->expectException(NotFoundHttpException::class);
        $this->download($this->bob);
    }
}
