<?php

namespace App\Tests\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\Projects\Project;
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

final class FinancialDraftActionsTest extends TestCase
{
    private Kernel $kernel;
    private EntityManagerInterface $em;
    private Session $session;

    protected function setUp(): void
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $this->em = $this->kernel->getContainer()->get('doctrine')->getManager();

        $classes = array_filter(
            $this->em->getMetadataFactory()->getAllMetadata(),
            static fn ($m): bool => str_contains($m->getName(), 'FinancialAnalysis') || in_array($m->getName(), [Project::class, Utilisateur::class, ProjectAssignment::class, Task::class], true)
        );
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->set('user', ['id' => 1, 'role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    private function token(): string
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        $request = Request::create('/');
        $request->setSession($this->session);
        $container->get('request_stack')->push($request);
        $value = $container->get('security.csrf.token_manager')->getToken('fa_write')->getValue();
        $container->get('request_stack')->pop();

        return $value;
    }

    private function post(string $uri): Response
    {
        $request = Request::create($uri, 'POST', ['_token' => $this->token()]);
        $request->headers->set('Accept', 'text/html');
        $request->setSession($this->session);

        return $this->kernel->handle($request, 1, true);
    }

    private function draft(string $status): ExpenseDraft
    {
        $user = (new Utilisateur())->setPrenom('A')->setNom('B')->setEmail('a@b.test')->setRole('employee')->setPassword('x')->setStatut('active')->setDateInscription(new \DateTime());
        $project = (new Project())->setName('P')->setDescription('d')->setCreatedBy(1);
        $budget = (new ProjectBudget())->setName('B')->setTotalBudget('100')->setActualSpend('0')->setStatus('ON TRACK')
            ->setDueDate(new \DateTime('2026-06-01'))->setProject($project);
        $draft = (new ExpenseDraft())->setAmount(10.0)->setDescription('x')->setStatus($status)->setCreatedAt(new \DateTimeImmutable())
            ->setCategory('OTHER')->setSubject('s')->setCreatedBy($user)->setProjectBudgetRelated($budget);
        foreach ([$user, $project, $budget, $draft] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        return $draft;
    }

    public function testUnknownActionIsNotARoute(): void
    {
        $this->assertSame(404, $this->post('/apps-financial-analysis/consultant/draft/1/delete-everything')->getStatusCode());
    }

    public function testApproveWorksOnceThenIsRefusedForAnAlreadyHandledDraft(): void
    {
        $draft = $this->draft('FLAGGED');
        $uri = '/apps-financial-analysis/consultant/draft/' . $draft->getId() . '/approve';

        $this->post($uri);
        $this->em->clear();
        $this->assertSame('APPROVED', $this->em->find(ExpenseDraft::class, $draft->getId())->getStatus());

        $this->em->getRepository(ExpenseDraft::class)->find($draft->getId())->setStatus('REJECTED');
        $this->em->flush();
        $this->post($uri); // a replayed approve must not undo a rejection
        $this->em->clear();
        $this->assertSame('REJECTED', $this->em->find(ExpenseDraft::class, $draft->getId())->getStatus());
    }

    public function testOnlyAnApprovedDraftBecomesATransaction(): void
    {
        $draft = $this->draft('FLAGGED');
        $this->post('/apps-financial-analysis/consultant/draft/' . $draft->getId() . '/to_transaction');
        $this->em->clear();

        $this->assertNotNull($this->em->find(ExpenseDraft::class, $draft->getId()), 'a flagged draft is kept');
    }
}
