<?php

namespace App\Tests\Controller;

use App\Entity\ResourcesManagement\Resource;
use App\Form\ResourcesManagement\ResourceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

final class ResourceImageUploadTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function imageFieldIsValid(string $contents, string $clientName): bool
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $contents);

        // A validator without attribute mapping runs only the form field's own constraints
        // (the entity's UniqueEntity rule needs Doctrine and is not relevant here).
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(ResourceType::class, new Resource());

        /** @var list<\Symfony\Component\Validator\Constraint> $constraints */
        $constraints = $form->get('image_path')->getConfig()->getOption('constraints');
        $file = new UploadedFile($path, $clientName, null, null, true);

        return count(Validation::createValidator()->validate($file, $constraints)) === 0;
    }

    public function testRealPngIsAccepted(): void
    {
        $this->assertTrue($this->imageFieldIsValid((string) base64_decode(self::PNG), 'logo.png'));
    }

    public function testSvgWithScriptIsRejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->assertFalse($this->imageFieldIsValid($svg, 'logo.svg'));
    }

    public function testHtmlAndPhpRenamedAsPngAreRejected(): void
    {
        $this->assertFalse($this->imageFieldIsValid('<html><script>alert(1)</script></html>', 'logo.png'));
        $this->assertFalse($this->imageFieldIsValid('<?php system($_GET["c"]);', 'logo.png'));
    }
}
