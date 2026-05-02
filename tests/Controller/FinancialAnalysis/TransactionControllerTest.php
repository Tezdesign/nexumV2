<?php

namespace App\Tests\Controller\FinancialAnalysis;

use App\Entity\FinancialAnalysis\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransactionControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<Transaction> */
    private EntityRepository $transactionRepository;
    private string $path = '/financial/analysis/transaction/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->transactionRepository = $this->manager->getRepository(Transaction::class);

        foreach ($this->transactionRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Transaction index');
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        // Since it's a standard symfony form, we'd guess the form name
        // is "transaction". The fields from the entity are reference, cost, date_stamp, expense_category, description
        $this->client->submitForm('Save', [
            'transaction[reference]' => 'TX-111222',
            'transaction[cost]' => '150.00',
            'transaction[date_stamp]' => '2025-10-10', // Depends on the form field type
            'transaction[expense_category]' => 'Travel',
            'transaction[description]' => 'Flight ticket',
        ]);

        self::assertResponseRedirects('/financial/analysis/transaction/');

        self::assertSame(1, $this->transactionRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new Transaction();
        $fixture->setReference('TX-333444');
        $fixture->setCost('250.00');
        $fixture->setDateStamp(new \DateTime('2025-05-05'));
        $fixture->setExpenseCategory('Services');
        $fixture->setDescription('Consulting');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Transaction');
    }

    public function testEdit(): void
    {
        $fixture = new Transaction();
        $fixture->setReference('TX-555666');
        $fixture->setCost('350.00');
        $fixture->setDateStamp(new \DateTime('2025-06-06'));
        $fixture->setExpenseCategory('Hardware');
        $fixture->setDescription('Laptop');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'transaction[reference]' => 'TX-777888',
            'transaction[cost]' => '450.00',
            'transaction[expense_category]' => 'Software',
            'transaction[description]' => 'License',
        ]);

        self::assertResponseRedirects('/financial/analysis/transaction/');

        $updatedFixture = $this->transactionRepository->find($fixture->getId());

        self::assertSame('TX-777888', $updatedFixture->getReference());
        self::assertSame('450.00', $updatedFixture->getCost());
        self::assertSame('Software', $updatedFixture->getExpenseCategory());
        self::assertSame('License', $updatedFixture->getDescription());
    }

    public function testRemove(): void
    {
        $fixture = new Transaction();
        $fixture->setReference('TX-999000');
        $fixture->setCost('550.00');
        $fixture->setDateStamp(new \DateTime('2025-07-07'));
        $fixture->setExpenseCategory('Other');
        $fixture->setDescription('Misc');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/financial/analysis/transaction/');
        self::assertSame(0, $this->transactionRepository->count([]));
    }
}
