<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class QuizImageGenerator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir
    ) {
    }

    /**
     * @return array{filename: string, prompt: string, publicPath: string}
     */
    public function generateFromQuizData(
        string $question,
        string $r1,
        string $r2,
        string $r3,
        int $correct
    ): array {
        $token = $this->readEnvValue(['HF_TOKEN', 'HUGGINGFACE_API_KEY', 'HUGGINGFACE_TOKEN']);
        if ($token === '') {
            throw new BadRequestHttpException('Token Hugging Face introuvable. Ajoute HF_TOKEN dans .env.local puis redemarre le serveur Symfony.');
        }

        $prompt = $this->buildPrompt($question, $r1, $r2, $r3, $correct);
        // Ancienne URL api-inference.huggingface.co renvoie 410 Gone — utiliser le routeur Inference Providers.
        $model = $this->readEnvValue(['HF_IMAGE_MODEL']) ?: 'runwayml/stable-diffusion-v1-5';
        $modelSegment = rawurlencode($model);
        $url = sprintf('https://router.huggingface.co/hf-inference/models/%s', $modelSegment);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => $prompt,
            ],
            'timeout' => 120,
        ]);

        $contentType = strtolower($response->getHeaders(false)['content-type'][0] ?? '');
        $binary = $response->getContent(false);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400 || !str_contains($contentType, 'image/')) {
            $detail = $this->extractHfErrorMessage($binary, $contentType, $statusCode, $model);
            throw new BadRequestHttpException(
                $detail ?? 'Generation image echouee (Hugging Face). Verifie ton token (permission Inference Providers) et le modele HF_IMAGE_MODEL.'
            );
        }

        $filename = 'quiz_ai_' . uniqid('', true) . '.png';
        $targetDir = $this->projectDir . '/public/uploads/quiz';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new FileException('Impossible de creer le dossier uploads/quiz');
        }
        
        $targetPath = $targetDir . '/' . $filename;
        if (file_put_contents($targetPath, $binary) === false) {
            throw new FileException('Impossible d ecrire le fichier image genere');
        }

        return [
            'filename' => $filename,
            'prompt' => $prompt,
            'publicPath' => '/uploads/quiz/' . $filename,
        ];
    }

    /**
     * @param string[] $keys
     */
    private function readEnvValue(array $keys): string
    {
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function extractHfErrorMessage(string $body, string $contentType, int $statusCode, string $model): ?string
    {
        if ($statusCode === 404) {
            return sprintf(
                'Le modele "%s" n est pas disponible sur HF Inference. Mets un autre HF_IMAGE_MODEL dans .env.local (ex: runwayml/stable-diffusion-v1-5) puis redemarre le serveur.',
                $model
            );
        }

        if (!str_contains($contentType, 'json')) {
            $trimmed = trim($body);
            if ($trimmed !== '') {
                return 'Erreur Hugging Face: ' . mb_substr($trimmed, 0, 240);
            }
            return null;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        if (isset($data['error']) && is_string($data['error'])) {
            return $data['error'];
        }

        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        return null;
    }

    private function buildPrompt(string $question, string $r1, string $r2, string $r3, int $correct): string
    {
        return $this->buildHintStyleFrenchQuizPrompt([
            'question' => $question,
            'r1' => $r1,
            'r2' => $r2,
            'r3' => $r3,
            'correct' => $correct,
        ]);
    }

    /**
     * Construit un prompt français pour générer un INDICE VISUEL pédagogique
     * qui aide l'apprenant à déduire la bonne réponse sans la révéler textuellement.
     */
    private function buildHintStyleFrenchQuizPrompt(array $payload): string
    {
        $question = trim((string) ($payload['question'] ?? ''));
        $r1 = trim((string) ($payload['r1'] ?? ''));
        $r2 = trim((string) ($payload['r2'] ?? ''));
        $r3 = trim((string) ($payload['r3'] ?? ''));
        $correct = (int) ($payload['correct'] ?? 0);

        // Identification robuste de la bonne réponse
        $correctAnswer = match ($correct) {
            1 => $r1,
            2 => $r2,
            3 => $r3,
            default => '',
        };

        // Fallback si correct invalide
        if ($correctAnswer === '') {
            error_log('[QuizImageGenerator] ATTENTION: correct invalide (' . $correct . '), utilisation de r1 comme fallback');
            $correctAnswer = $r1;
        }

        // Construction du prompt d'indice visuel français
        $prompt = sprintf(
            "Crée une illustration pédagogique en français, sans texte visible, qui sert d'indice visuel pour aider un apprenant à déduire la bonne réponse à cette question : \"%s\". " .
            "La bonne réponse conceptuelle est : \"%s\". " .
            "L'image doit montrer une scène cohérente avec des indices visuels concrets liés à ce concept correct, sans jamais afficher la question, les réponses, ni une interface de quiz. " .
            "La scène doit contenir des objets, actions, symboles ou contexte qui suggèrent intelligemment la bonne réponse. " .
            "L'indice doit être subtil mais utile, guidant la réflexion sans spoiler la réponse écrite. " .
            "Style : éducatif, semi-réaliste, propre, moderne, composition centrée, éclairage naturel doux. " .
            "Une seule scène pédagogique cohérente, pas d'éléments décoratifs inutiles, focus sur l'indice visuel utile.",
            $question,
            $correctAnswer
        );

        // Negative prompt strict pour interdire tous les éléments de quiz et texte
        $negativePrompt = "text, letters, numbers, writing, quiz interface, buttons, checkboxes, multiple choice, " .
                         "question text, answer options, R1, R2, R3, cards, blackboard, whiteboard, speech bubbles, text bubbles, " .
                         "UI elements, interface, forms, slides, posters, charts, diagrams with labels, " .
                         "watermarks, signatures, borders, frames, decorative elements, " .
                         "cartoon style, abstract, blurry, low quality, text overlays, labels, titles, subtitles";

        // Combiner prompt et negative prompt
        $finalPrompt = $prompt . ". Negative prompt: " . $negativePrompt;

        // Log temporaire du prompt final pour debug
        error_log('[QuizImageGenerator] Prompt Hugging Face envoyé : ' . $finalPrompt);

        return $finalPrompt;
    }
}

