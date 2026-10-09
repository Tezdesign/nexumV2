<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Génère un quiz depuis un PDF via un script Python local (sans API externe).
 *
 * Contrat attendu côté Python:
 * - En cas de succès: le script écrit sur stdout un JSON (objet) représentant le quiz.
 * - En cas d'erreur: le script écrit sur stderr un JSON {"error": "..."} et exit code != 0.
 *
 * Cette classe est autonome (pas de dépendance Doctrine).
 */
final class PythonQuizGeneratorService
{
    private string $projectDir;

    /**
     * @param string|null $pythonBin  QUIZ_PYTHON_BIN: interpreter to run (a path, or a name found on the PATH)
     * @param string|null $scriptPath QUIZ_GENERATOR_SCRIPT: the generate_quiz.py script
     *
     * Without them the service looks for the folder the feature was first written in (`pfe_ferdawes_linedata_fst`,
     * a virtualenv that is not part of the repository), then for `python/generate_quiz.py`.
     */
    public function __construct(
        string $projectDir,
        private readonly ?string $pythonBin = null,
        private readonly ?string $scriptPath = null,
    ) {
        $this->projectDir = rtrim($projectDir, DIRECTORY_SEPARATOR);
    }

    public function getPythonExecutablePath(): string
    {
        if ($this->pythonBin !== null && trim($this->pythonBin) !== '') {
            return trim($this->pythonBin);
        }

        $venv = $this->projectDir . '/pfe_ferdawes_linedata_fst/venv';
        foreach (['/Scripts/python.exe', '/bin/python'] as $candidate) { // Windows, then Linux and macOS
            if (is_file($venv . $candidate)) {
                return $venv . $candidate;
            }
        }

        return 'python';
    }

    public function getScriptPath(): string
    {
        if ($this->scriptPath !== null && trim($this->scriptPath) !== '') {
            return trim($this->scriptPath);
        }

        foreach (['/pfe_ferdawes_linedata_fst/generate_quiz.py', '/python/generate_quiz.py'] as $candidate) {
            if (is_file($this->projectDir . $candidate)) {
                return $this->projectDir . $candidate;
            }
        }

        return $this->projectDir . '/python/generate_quiz.py';
    }

    public function getTmpDir(): string
    {
        return $this->projectDir . '/var/tmp';
    }

    /** Where an uploaded PDF waits for the generator. Private, and emptied by the caller. */
    public function getUploadDir(): string
    {
        return $this->projectDir . '/var/quiz_ai';
    }

    /**
     * @return array<string, mixed> Quiz JSON décodé en tableau associatif.
     */
    public function generateFromPdf(string $absolutePdfPath, int $questions = 5, int $timeoutSeconds = 600): array
    {
        // Disable PHP execution time limit for long-running LLM processes
        set_time_limit(0);
        
        // Debug: Log the exact PDF path
        error_log("PythonQuizGenerator: Processing PDF path: " . $absolutePdfPath);
        
        if ($absolutePdfPath === '' || !is_file($absolutePdfPath)) {
            throw new \RuntimeException('PDF introuvable: ' . $absolutePdfPath);
        }

        $python = $this->resolvePython();
        $script = $this->getScriptPath();
        $tmpDir = $this->getTmpDir();
        $uploadDir = $this->getUploadDir();

        if (!is_file($script)) {
            throw new \RuntimeException('Le générateur de quiz n’est pas configuré: script introuvable. Définissez QUIZ_GENERATOR_SCRIPT (et QUIZ_PYTHON_BIN si besoin) dans .env.local.');
        }

        $this->ensureDirectoryExists($tmpDir);
        $this->ensureDirectoryExists($uploadDir);

        $process = new Process([
            $python,
            $script,
            $absolutePdfPath,
            '--questions',
            (string) $questions,
        ]);

        // Très important sur Windows
        $process->setWorkingDirectory($this->projectDir);
        $process->setTimeout(1200);
        $process->setIdleTimeout(1200);

        // Force les dossiers temporaires pour éviter l’erreur var/tmp/*.lock
        $process->setEnv([
            'TMP' => $tmpDir,
            'TEMP' => $tmpDir,
            'TMPDIR' => $tmpDir,
        ]);

        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Échec d’exécution Python: ' . $e->getMessage(), 0, $e);
        }

        $stdout = (string) $process->getOutput();
        $stderr = (string) $process->getErrorOutput();
        
        // Debug: Log raw outputs
        error_log("PythonQuizGenerator: Process exit code: " . $process->getExitCode());
        error_log("PythonQuizGenerator: STDOUT: " . $this->compactText($stdout, 200));
        error_log("PythonQuizGenerator: STDERR: " . $this->compactText($stderr, 200));

        // Try to extract JSON from stdout first, even if process failed
        $payload = $this->tryDecodeFirstJsonObject($stdout);
        
        // If we got valid JSON from stdout, use it regardless of stderr warnings
        if (is_array($payload) && !isset($payload['error'])) {
            error_log("PythonQuizGenerator: Successfully extracted JSON from stdout");
            return $payload;
        }
        
        // Process failed and no valid JSON in stdout, check for error in stderr
        if (!$process->isSuccessful()) {
            $errorPayload = $this->tryDecodeFirstJsonObject($stderr);

            if (is_array($errorPayload) && isset($errorPayload['error']) && is_string($errorPayload['error'])) {
                throw new \RuntimeException('Python: ' . $errorPayload['error']);
            }
            
            // Check if stderr contains only non-fatal llama warnings
            if ($this->containsOnlyLlamaWarnings($stderr)) {
                error_log("PythonQuizGenerator: Process failed but stderr contains only llama warnings");
                // Try to extract JSON from stdout one more time
                if (is_array($payload)) {
                    return $payload;
                }
            }

            $hint = $stderr !== '' ? $this->compactText($stderr) : $this->compactText($stdout);
            // Not ProcessFailedException: its message carries the full command line (server paths) and goes to the user's screen.
            throw new \RuntimeException($hint !== '' ? $hint : 'Le générateur de quiz a échoué (code ' . $process->getExitCode() . ').');
        }

        // Process succeeded but no valid JSON found
        $combined = trim($stdout . "\n" . $stderr);
        $excerpt = $this->compactText($combined);

        throw new \RuntimeException(
            'Impossible de lire le JSON retourné par Python.'
            . ($excerpt !== '' ? (' Extrait: ' . $excerpt) : '')
        );
    }

    /** A configured path must exist; a bare name such as `python` is looked up on the PATH. */
    private function resolvePython(): string
    {
        $python = $this->getPythonExecutablePath();
        if (str_contains($python, '/') || str_contains($python, '\\')) {
            if (!is_file($python)) {
                throw new \RuntimeException('Python introuvable: vérifiez QUIZ_PYTHON_BIN dans .env.local.');
            }

            return $python;
        }

        $found = (new ExecutableFinder())->find($python);
        if ($found === null) {
            throw new \RuntimeException('Python introuvable sur ce serveur. Installez-le ou définissez QUIZ_PYTHON_BIN dans .env.local.');
        }

        return $found;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException('Impossible de créer le dossier: ' . $path);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryDecodeFirstJsonObject(string $text): ?array
    {
        $text = $this->sanitizeProcessText($text);

        if ($text === '') {
            return null;
        }

        $direct = json_decode($text, true);
        if (is_array($direct)) {
            return $direct;
        }

        $json = $this->extractFirstJsonObjectString($text);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function sanitizeProcessText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Nettoyage des octets nuls souvent injectés par certains bindings natifs.
        $text = str_replace("\0", '', $text);

        // Supprime un éventuel BOM UTF-8 en tête.
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }

        // Tente de réparer l'encodage invalide sans casser Windows.
        if (!mb_check_encoding($text, 'UTF-8')) {
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
                if ($converted !== false) {
                    $text = $converted;
                }
            }

            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
        }

        // Retire les caractères de contrôle non imprimables qui perturbent json_decode.
        $text = preg_replace('/[^\P{C}\t\r\n]/u', '', $text) ?? $text;

        return trim($text);
    }

    private function extractFirstJsonObjectString(string $text): ?string
    {
        $len = strlen($text);
        $start = strpos($text, '{');

        if ($start === false) {
            return null;
        }

        $inString = false;
        $escaped = false;
        $depth = 0;

        for ($i = (int) $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($ch === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($ch === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === '{') {
                $depth++;
                continue;
            }

            if ($ch === '}') {
                $depth--;

                if ($depth === 0) {
                    $candidate = substr($text, (int) $start, $i - (int) $start + 1);
                    $decoded = json_decode($candidate, true);

                    if (is_array($decoded)) {
                        return $candidate;
                    }

                    $nextStart = strpos($text, '{', $start + 1);
                    if ($nextStart === false) {
                        return null;
                    }

                    $start = $nextStart;
                    $i = $start - 1;
                    $inString = false;
                    $escaped = false;
                    $depth = 0;
                }
            }
        }

        return null;
    }

    private function compactText(string $text, int $maxLen = 400): string
    {
        $text = $this->sanitizeProcessText($text);
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen) . '…';
    }
    
    private function containsOnlyLlamaWarnings(string $stderr): bool
    {
        // Check if stderr contains only llama.cpp warnings, not actual errors
        $llamaWarningPatterns = [
            '/llama_context.*n_ctx_seq.*n_ctx_train/',
            '/ggml_init_cublas/',
            '/AVX.*not detected/',
            '/AVX2.*not detected/',
            '/AVX512.*not detected/',
            '/AVX512_VBMI.*not detected/',
            '/AVX512_VNNI.*not detected/',
            '/FMA.*not detected/',
            '/NEON.*not detected/',
            '/ARM_FMA.*not detected/',
            '/F16C.*not detected/',
            '/FP16_VA.*not detected/',
            '/WASM_SIMD.*not detected/',
            '/BLAS.*not detected/',
            '/SSE3.*not detected/',
            '/SSSE3.*not detected/',
            '/VSX.*not detected/',
            '/MATMUL_INT8.*not detected/'
        ];
        
        $lines = explode("\n", trim($stderr));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            
            $isWarning = false;
            foreach ($llamaWarningPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $isWarning = true;
                    break;
                }
            }
            
            // If this line is not a warning and not empty, then it's a real error
            if (!$isWarning) {
                return false;
            }
        }
        
        return true;
    }
}