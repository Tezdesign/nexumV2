<?php

namespace App\Tests\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BudgetProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<BudgetProfile> */
    private EntityRepository $budgetProfileRepository;
    private string $path = '/financial/analysis/budget/profile/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->budgetProfileRepository = $this->manager->getRepository(BudgetProfile::class);

        foreach ($this->budgetProfileRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('BudgetProfile index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'budget_profile[fiscal_year]' => 'Testing',
            'budget_profile[budget_disposable]' => 'Testing',
            'budget_profile[total_expense]' => 'Testing',
            'budget_profile[margin_profit]' => 'Testing',
        ]);

        self::assertResponseRedirects('/financial/analysis/budget/profile');

        self::assertSame(1, $this->budgetProfileRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }

    public function testShow(): void
    {
        $fixture = new BudgetProfile();
        $fixture->setFiscalYear('My Title');
        $fixture->setBudgetDisposable('My Title');
        $fixture->setTotalExpense('My Title');
        $fixture->setMarginProfit('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('BudgetProfile');

        // Use assertions to check that the properties are properly displayed.
        $this->markTestIncomplete('This test was generated');
    }

    public function testEdit(): void
    {
        $fixture = new BudgetProfile();
        $fixture->setFiscalYear('Value');
        $fixture->setBudgetDisposable('Value');
        $fixture->setTotalExpense('Value');
        $fixture->setMarginProfit('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'budget_profile[fiscal_year]' => 'Something New',
            'budget_profile[budget_disposable]' => 'Something New',
            'budget_profile[total_expense]' => 'Something New',
            'budget_profile[margin_profit]' => 'Something New',
        ]);

        self::assertResponseRedirects('/financial/analysis/budget/profile');

        $fixture = $this->budgetProfileRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getFiscalYear());
        self::assertSame('Something New', $fixture[0]->getBudgetDisposable());
        self::assertSame('Something New', $fixture[0]->getTotalExpense());
        self::assertSame('Something New', $fixture[0]->getMarginProfit());

        $this->markTestIncomplete('This test was generated');
    }

    public function testRemove(): void
    {
        $fixture = new BudgetProfile();
        $fixture->setFiscalYear('Value');
        $fixture->setBudgetDisposable('Value');
        $fixture->setTotalExpense('Value');
        $fixture->setMarginProfit('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/financial/analysis/budget/profile');
        self::assertSame(0, $this->budgetProfileRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }
}
