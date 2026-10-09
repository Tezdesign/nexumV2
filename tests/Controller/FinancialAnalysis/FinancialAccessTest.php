<?php

namespace App\Tests\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\FinancialAnalysis\Transaction;
use App\Entity\Projects\Project;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;
use App\Service\FinancialAnalysis\BudgetDashboardService;
use App\Service\FinancialAnalysis\BudgetTrendCacheService;
use App\Entity\Projects\ProjectAssignment;
use App\Entity\Tasks\Task;
use App\Entity\UserHandling\Utilisateur;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class FinancialAccessTest extends TestCase
{
    private Kernel $kernel;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $this->entityManager = $this->kernel->getContainer()->get('doctrine')->getManager();

        $classes = array_filter(
            $this->entityManager->getMetadataFactory()->getAllMetadata(),
            static fn ($m): bool => str_contains($m->getName(), 'FinancialAnalysis') || in_array($m->getName(), [Project::class, Utilisateur::class, ProjectAssignment::class, Task::class], true)
        );
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    /** @param array<string, string> $post */
    private function call(string $method, string $uri, ?string $role = null, array $post = []): Response
    {
        $request = Request::create($uri, $method, $post);
        $request->headers->set('Accept', 'text/html');
        $session = new Session(new MockArraySessionStorage());
        if ($role !== null) {
            $session->set('user', ['id' => 1, 'role' => $role]);
        }
        $request->setSession($session);

        return $this->kernel->handle($request, 1, true);
    }

    private function profile(string $year, string $start, string $end): BudgetProfile
    {
        $profile = (new BudgetProfile())->setFiscalYear($year)->setBudgetDisposable('1000')->setTotalExpense('0')
            ->setBaseCurrency('USD')->setStartDate(new \DateTime($start))->setEndDate(new \DateTime($end))->setStatus('ACTIVE');
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }

    private function budget(string $due): ProjectBudget
    {
        $project = (new Project())->setName('P')->setDescription('d')->setCreatedBy(1);
        $this->entityManager->persist($project);
        $budget = (new ProjectBudget())->setName('B')->setTotalBudget('100')->setActualSpend('0')->setStatus('ON TRACK')
            ->setDueDate(new \DateTime($due))->setProject($project);
        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        return $budget;
    }

    /** @return iterable<string, array{string, string}> */
    public static function routes(): iterable
    {
        yield 'landing' => ['GET', '/apps-financial-analysis'];
        yield 'profile page' => ['GET', '/apps-financial-analysis/profile/1'];
        yield 'delete profile' => ['POST', '/apps-financial-analysis/profile/1/delete'];
        yield 'delete budget' => ['POST', '/apps-financial-analysis/budget/1/delete'];
        yield 'bulk delete' => ['POST', '/apps-financial-analysis/budget/1/transactions/bulk-delete'];
        yield 'consultant action' => ['POST', '/apps-financial-analysis/consultant/draft/1/approve'];
        yield 'crud profiles' => ['GET', '/financial/analysis/budget/profile'];
        yield 'crud budgets' => ['GET', '/financial/analysis/project/budget'];
        yield 'crud transactions' => ['GET', '/financial/analysis/transaction'];
        yield 'crud delete' => ['POST', '/financial/analysis/transaction/1'];
    }

    /** @dataProvider routes */
    public function testVisitorIsSentToTheWelcomePage(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/welcome', $response->headers->get('Location'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function adminOnly(): iterable
    {
        yield 'delete profile' => ['POST', '/apps-financial-analysis/profile/1/delete'];
        yield 'delete budget' => ['POST', '/apps-financial-analysis/budget/1/delete'];
        yield 'bulk delete' => ['POST', '/apps-financial-analysis/budget/1/transactions/bulk-delete'];
        yield 'consultant action' => ['POST', '/apps-financial-analysis/consultant/draft/1/approve'];
        yield 'crud delete' => ['POST', '/financial/analysis/transaction/1'];
    }

    /** @dataProvider adminOnly */
    public function testEmployeeCannotUseDestructiveRoutes(string $method, string $uri): void
    {
        $this->assertSame(403, $this->call($method, $uri, 'employee')->getStatusCode());
    }

    public function testAdminPostsWithoutTokenAreRefusedAndNothingIsDeleted(): void
    {
        $profile = $this->profile('2026', '2026-01-01', '2026-12-31');
        $budget = $this->budget('2026-06-01');

        $this->assertSame(403, $this->call('POST', '/apps-financial-analysis/profile/' . $profile->getId() . '/delete', 'admin', ['confirm_year' => '2026'])->getStatusCode());
        $this->assertSame(403, $this->call('POST', '/apps-financial-analysis/budget/' . $budget->getId() . '/delete', 'admin')->getStatusCode());

        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(BudgetProfile::class, $profile->getId()));
        $this->assertNotNull($this->entityManager->find(ProjectBudget::class, $budget->getId()));
    }

    public function testFiscalYearDeleteKeepsBudgetsThatAnotherYearStillOwns(): void
    {
        $this->profile('2025', '2025-07-01', '2026-06-30');
        $overlapping = $this->profile('2026', '2026-01-01', '2026-12-31');
        $shared = $this->budget('2026-03-01');     // inside both years
        $mine = $this->budget('2025-09-01');       // only in 2025

        $this->entityManager->clear();
        $profile2025 = $this->entityManager->getRepository(BudgetProfile::class)->findOneBy(['fiscal_year' => '2025']);
        $container = $this->kernel->getContainer()->get('test.service_container');
        // SQLite cannot run the unquoted `transaction` DELETE that MySQL accepts, so only that repository is replaced.
        $service = new BudgetDashboardService(
            $container->get(ProjectBudgetRepository::class),
            $container->get(BudgetProfileRepository::class),
            $this->createMock(TransactionRepository::class),
            $container->get(BudgetTrendCacheService::class),
        );
        $service->handleFullFiscalYearDeletionCascade($profile2025);
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(ProjectBudget::class, $mine->getId()));
        $this->assertNotNull($this->entityManager->find(ProjectBudget::class, $shared->getId()));
        $this->assertNotNull($this->entityManager->find(BudgetProfile::class, $overlapping->getId()));
        $this->assertNull($this->entityManager->getRepository(BudgetProfile::class)->findOneBy(['fiscal_year' => '2025']));
    }
}
