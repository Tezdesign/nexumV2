<?php

namespace App\Tests\Controller;

use App\Entity\Formation;
use App\Entity\UserHandling\Utilisateur;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SmallFixesTest extends TestCase
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
            static fn ($m): bool => in_array($m->getName(), [Utilisateur::class, Formation::class, \App\Entity\Participer::class], true)
        );
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
        $this->session = new Session(new MockArraySessionStorage());
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    private function user(): Utilisateur
    {
        $user = (new Utilisateur())->setPrenom('Ann')->setNom('Lee')->setEmail('ann@nexum.test')->setRole('employee')
            ->setPassword('old-secret')->setStatut('active')->setDateInscription(new \DateTime());
        $this->em->persist($user);
        $this->em->flush();
        $this->session->set('user', ['id' => $user->getId(), 'role' => 'employee']);

        return $user;
    }

    private function token(string $id): string
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        $request = Request::create('/');
        $request->setSession($this->session);
        $container->get('request_stack')->push($request);
        $value = $container->get('security.csrf.token_manager')->getToken($id)->getValue();
        $container->get('request_stack')->pop();

        return $value;
    }

    /** @param array<string, string> $fields */
    private function saveProfile(array $fields): void
    {
        $post = $fields + ['_token' => $this->token('profile'), 'nom' => 'Lee', 'prenom' => 'Ann', 'email' => 'ann@nexum.test', 'telephone' => '', 'departement' => ''];
        $request = Request::create('/account', 'POST', $post);
        $request->headers->set('Accept', 'text/html');
        $request->setSession($this->session);
        $this->kernel->handle($request, 1, true);
        $this->em->clear();
    }

    private function storedPassword(Utilisateur $user): string
    {
        return (string) $this->em->find(Utilisateur::class, $user->getId())->getPassword();
    }

    public function testPasswordChangeNeedsTheCurrentPassword(): void
    {
        $user = $this->user();

        $this->saveProfile(['new_password' => 'brand-new', 'confirm_password' => 'brand-new']);
        $this->assertSame('old-secret', $this->storedPassword($user));

        $this->saveProfile(['current_password' => 'wrong', 'new_password' => 'brand-new', 'confirm_password' => 'brand-new']);
        $this->assertSame('old-secret', $this->storedPassword($user));

        $this->saveProfile(['current_password' => 'old-secret', 'new_password' => 'brand-new', 'confirm_password' => 'brand-new']);
        $this->assertSame('brand-new', $this->storedPassword($user));
    }

    public function testOtherFieldsStillSaveWithoutAPasswordChange(): void
    {
        $user = $this->user();
        $this->saveProfile(['telephone' => '+21611111111']);

        $this->assertSame('+21611111111', $this->em->find(Utilisateur::class, $user->getId())->getTelephone());
    }

    public function testTooLongFieldsAreRefusedNotSent500(): void
    {
        $user = $this->user();
        $this->saveProfile(['nom' => str_repeat('x', 51)]);

        $this->assertSame('Lee', $this->em->find(Utilisateur::class, $user->getId())->getNom());
    }

    public function testReportRoutesSendVisitorsToWelcome(): void
    {
        $request = Request::create('/rapport/1', 'GET');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $this->kernel->handle($request, 1, true);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/welcome', $response->headers->get('Location'));
    }

    public function testExportKeepsFormulaLookingTextAsText(): void
    {
        $formation = (new Formation())->setTitre('=1+1')->setDescription('+SUM(A1)');
        $this->em->persist($formation);
        $this->em->flush();
        $this->session->set('user', ['id' => 1, 'role' => 'admin']);

        $request = Request::create('/formation/admin/export/xls', 'GET');
        $request->setSession($this->session);
        $response = $this->kernel->handle($request, 1, true);
        $this->assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $xlsx = (string) ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'xls') ?: '';
        file_put_contents($path, $xlsx);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
    }
}
