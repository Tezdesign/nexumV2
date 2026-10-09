<?php

namespace App\Tests\Service;

use App\Service\PythonQuizGeneratorService;
use PHPUnit\Framework\TestCase;

final class PythonQuizGeneratorServiceTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/nexum-quiz-'.bin2hex(random_bytes(4));
        mkdir($this->project.'/var', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->project.'/{var/*,python/*,pfe_ferdawes_linedata_fst/venv/bin/*,pfe_ferdawes_linedata_fst/venv/Scripts/*,pfe_ferdawes_linedata_fst/*}', GLOB_BRACE) as $file) {
            @unlink($file);
        }
    }

    private function touch(string $relative): string
    {
        $path = $this->project.'/'.$relative;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, '');

        return $path;
    }

    private function pdf(): string
    {
        $path = $this->project.'/var/test.pdf';
        file_put_contents($path, '%PDF-1.4');

        return $path;
    }

    public function testConfiguredPathsWin(): void
    {
        $service = new PythonQuizGeneratorService($this->project, '/opt/python3', '/srv/generate_quiz.py');

        $this->assertSame('/opt/python3', $service->getPythonExecutablePath());
        $this->assertSame('/srv/generate_quiz.py', $service->getScriptPath());
    }

    public function testWithoutConfigurationItFallsBackToPythonOnThePathAndTheRepositoryScriptLocation(): void
    {
        $service = new PythonQuizGeneratorService($this->project);

        $this->assertSame('python', $service->getPythonExecutablePath());
        $this->assertSame($this->project.'/python/generate_quiz.py', $service->getScriptPath());
    }

    public function testTheOldWindowsVirtualenvAndTheUnixOneAreStillFoundWhenPresent(): void
    {
        $windows = $this->touch('pfe_ferdawes_linedata_fst/venv/Scripts/python.exe');
        $script = $this->touch('pfe_ferdawes_linedata_fst/generate_quiz.py');

        $service = new PythonQuizGeneratorService($this->project);
        $this->assertSame($windows, $service->getPythonExecutablePath());
        $this->assertSame($script, $service->getScriptPath());

        unlink($windows);
        $unix = $this->touch('pfe_ferdawes_linedata_fst/venv/bin/python');
        $this->assertSame($unix, $service->getPythonExecutablePath());
    }

    public function testUploadedPdfsWaitInAPrivateFolder(): void
    {
        $this->assertSame($this->project.'/var/quiz_ai', (new PythonQuizGeneratorService($this->project))->getUploadDir());
    }

    public function testMissingScriptGivesAnActionableMessageWithoutServerPaths(): void
    {
        $service = new PythonQuizGeneratorService($this->project, PHP_BINARY, null);

        try {
            $service->generateFromPdf($this->pdf());
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('QUIZ_GENERATOR_SCRIPT', $e->getMessage());
            $this->assertStringNotContainsString($this->project, $e->getMessage());
        }
    }

    public function testUnknownInterpreterGivesAnActionableMessage(): void
    {
        $script = $this->touch('python/generate_quiz.py');
        $service = new PythonQuizGeneratorService($this->project, 'no-such-python-binary', $script);

        $this->expectExceptionMessage('QUIZ_PYTHON_BIN');
        $service->generateFromPdf($this->pdf());
    }

    public function testQuizJsonIsReadFromTheConfiguredScript(): void
    {
        // Any program can stand in for python: here PHP runs a script that prints the JSON the real one prints.
        $script = $this->project.'/python/generate_quiz.php';
        @mkdir(dirname($script), 0775, true);
        file_put_contents($script, '<?php echo json_encode(["questions" => [["type" => "mcq", "question" => "Q?", "pdf" => basename($argv[1])]]]);');
        $service = new PythonQuizGeneratorService($this->project, PHP_BINARY, $script);

        $quiz = $service->generateFromPdf($this->pdf());

        $this->assertSame('Q?', $quiz['questions'][0]['question']);
        $this->assertSame('test.pdf', $quiz['questions'][0]['pdf'], 'the script receives the PDF path');
    }

    public function testScriptFailureMessageHasNoCommandLineOrServerPath(): void
    {
        $script = $this->project.'/python/boom.php';
        @mkdir(dirname($script), 0775, true);
        file_put_contents($script, '<?php fwrite(STDERR, "model file missing"); exit(3);');
        $service = new PythonQuizGeneratorService($this->project, PHP_BINARY, $script);

        try {
            $service->generateFromPdf($this->pdf());
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('model file missing', $e->getMessage());
            $this->assertStringNotContainsString($this->project, $e->getMessage());
            $this->assertStringNotContainsString(PHP_BINARY, $e->getMessage());
        }
    }
}
