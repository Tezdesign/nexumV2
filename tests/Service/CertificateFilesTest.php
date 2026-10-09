<?php

namespace App\Tests\Service;

use App\Service\CertificateService;
use App\Service\QrService;
use PHPUnit\Framework\TestCase;

final class CertificateFilesTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/nexum-cert-'.bin2hex(random_bytes(4));
        mkdir($this->project.'/public', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->project.'/{var/certificates,var/qr,public}/*', GLOB_BRACE) as $file) {
            @unlink($file);
        }
    }

    private function person(int $id, string $name): object
    {
        // generate() only reads getId(), getNom() and getTitre(), which both entities answer with a name.
        $class = $name === 'PHP' ? \App\Entity\Formation::class : \App\Entity\UserHandling\Utilisateur::class;
        $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($class, 'id'))->setValue($object, $id);
        $name === 'PHP' ? $object->setTitre($name) : $object->setNom($name);

        return $object;
    }

    public function testCertificateIsWrittenOutsidePublicWithOneFilePerUserAndFormation(): void
    {
        $service = new CertificateService($this->project);
        $user = $this->person(7, 'Ada');
        $formation = $this->person(3, 'PHP');

        $first = $service->generate($user, $formation, 'CERT-1');
        $second = $service->generate($user, $formation, 'CERT-1');

        $this->assertSame($this->project.'/var/certificates/cert_7_3.pdf', $first);
        $this->assertSame($first, $second, 'a new certificate replaces the old one');
        $this->assertCount(1, glob($this->project.'/var/certificates/*'));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($first));
        $this->assertSame([], glob($this->project.'/public/*'), 'nothing is written under public/');
    }

    public function testDifferentUsersGetDifferentFiles(): void
    {
        $service = new CertificateService($this->project);
        $formation = $this->person(3, 'PHP');

        $this->assertNotSame(
            $service->pathFor($this->person(7, 'Ada'), $formation),
            $service->pathFor($this->person(8, 'Bob'), $formation)
        );
    }

    public function testQrCodeIsEmbeddedFromAPrivateFolder(): void
    {
        $qr = (new QrService($this->project))->generate('CERTIFICATE');

        $this->assertNotNull($qr);
        $this->assertStringStartsWith($this->project.'/var/qr/', $qr);
        $this->assertFileExists($qr);

        $pdf = (new CertificateService($this->project))->generate($this->person(7, 'Ada'), $this->person(3, 'PHP'), 'CERT-1', $qr);
        $this->assertFileExists($pdf);
    }
}
