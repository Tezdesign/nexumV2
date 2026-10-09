<?php

namespace App\Controller;

use App\Attribute\RequireAdmin;
use App\Entity\Formation;
use App\Entity\Quiz;
use App\Form\PdfQuizUploadType;
use App\Form\QuizType;
use App\Service\PythonQuizGeneratorService;
use App\Service\QuizImageGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use DateTime;

#[Route('/quiz')]
#[RequireAdmin]
final class QuizController extends AbstractController
{
    #[Route('/ai/upload', name: 'app_quiz_ai_upload', methods: ['GET'])]
    public function aiUpload(Request $request): Response
    {
        $form = $this->createForm(PdfQuizUploadType::class);

        return $this->render('quiz/ai_upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ai/generate', name: 'app_quiz_ai_generate', methods: ['POST'])]
    public function aiGenerate(
        Request $request,
        PythonQuizGeneratorService $pythonQuizGeneratorService,
        Filesystem $filesystem
    ): Response {
        $form = $this->createForm(PdfQuizUploadType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger le formulaire et réessayer.');

            return $this->render('quiz/ai_upload.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        /** @var UploadedFile|null $pdf */
        $pdf = $form->get('pdf')->getData();
        if (!$pdf instanceof UploadedFile) {
            $this->addFlash('error', 'Fichier PDF manquant.');
            return $this->redirectToRoute('app_quiz_ai_upload');
        }

        // Stockage temporaire (sans base de données)
        // Private folder: the PDF is only needed while the generator reads it.
        $uploadDir = $pythonQuizGeneratorService->getUploadDir();
        try {
            if (!$filesystem->exists($uploadDir)) {
                $filesystem->mkdir($uploadDir, 0775);
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible de créer le dossier temporaire: ' . $e->getMessage());
            return $this->redirectToRoute('app_quiz_ai_upload');
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) pathinfo($pdf->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'document';
        $fileName = 'quiz_ai_' . $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';

        try {
            $pdf->move($uploadDir, $fileName);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Upload échoué: ' . $e->getMessage());
            return $this->redirectToRoute('app_quiz_ai_upload');
        }

        $absolutePdfPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        try {
            $quiz = $pythonQuizGeneratorService->generateFromPdf($absolutePdfPath, 5, 600);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Génération IA échouée: ' . $e->getMessage());
            return $this->redirectToRoute('app_quiz_ai_upload');
        } finally {
            try {
                $filesystem->remove($absolutePdfPath);
            } catch (\Throwable) {
                // A leftover temporary file is harmless, it is outside public/.
            }
        }

        return $this->render('quiz/ai_result.html.twig', [
            'quiz' => $quiz,
        ]);
    }

    #[Route('/generate-image', name: 'app_quiz_generate_image', methods: ['GET', 'POST'])]
    public function generateImage(Request $request, QuizImageGenerator $quizImageGenerator): Response
    {
        if ($request->isMethod('GET')) {
            $accept = (string) $request->headers->get('Accept', '');
            if (str_contains($accept, 'application/json')) {
                return new JsonResponse([
                    'ok' => false,
                    'message' => 'Cette URL ne s\'ouvre pas dans le navigateur. Utilisez le bouton « Créer une image » (requête POST avec JSON : question, r1, r2, r3, correct).',
                ]);
            }

            $this->addFlash(
                'warning',
                'La génération d\'image ne s\'ouvre pas comme une page web. Ouvre une formation en Admin, modal « Ajouter Quiz », puis clique sur « Créer une image ».'
            );

            return $this->redirectToRoute('app_formation_index');
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Payload invalide'], 400);
        }

        $question = trim((string) ($payload['question'] ?? ''));
        $r1       = trim((string) ($payload['r1'] ?? ''));
        $r2       = trim((string) ($payload['r2'] ?? ''));
        $r3       = trim((string) ($payload['r3'] ?? ''));
        $correct  = (int) ($payload['correct'] ?? 0);

        if ($question === '' || $r1 === '' || $r2 === '' || $r3 === '' || !in_array($correct, [1, 2, 3], true)) {
            return new JsonResponse([
                'error' => 'Remplis question, reponse 1/2/3 et bonne reponse avant generation.'
            ], 422);
        }

        try {
            $generated = $quizImageGenerator->generateFromQuizData($question, $r1, $r2, $r3, $correct);

            return new JsonResponse([
                'success'  => true,
                'filename' => $generated['filename'],
                'url'      => $generated['publicPath'],
                'prompt'   => $generated['prompt'],
            ]);
        } catch (\Throwable $e) {
            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            return new JsonResponse(['error' => $e->getMessage()], $status);
        }
    }

    #[Route('/formation/{id}/new', name: 'app_quiz_new', methods: ['POST'])]
    public function new(
        Formation $formation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $quiz = new Quiz();
        $quiz->setFormation($formation);

        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ FIX : lire generatedImage dans le namespace quiz[] comme dans edit()
            $quizData      = $request->request->all('quiz');
            $generatedImage = trim((string) ($quizData['generatedImage'] ?? ''));

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                // Fichier uploadé manuellement → priorité
                $fileName = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/quiz',
                    $fileName
                );
                $quiz->setImage($fileName);
            } elseif ($generatedImage !== '') {
                // Image générée par l'IA
                $quiz->setImage($generatedImage);
            }

            $em->persist($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz ajouté avec succès.');
        }

        return $this->redirectToRoute('app_formation_show_admin', [
            'id' => $formation->getId(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_quiz_edit', methods: ['POST'])]
    public function edit(
        Quiz $quiz,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('edit_quiz_' . $quiz->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_formation_show_admin', ['id' => $quiz->getFormation()->getId()]);
        }

        $data = $request->request->all('quiz');

        $quiz->setQuestion($data['question']);
        $quiz->setR1($data['r1']);
        $quiz->setR2($data['r2']);
        $quiz->setR3($data['r3']);
        $quiz->setCorrect((int) $data['correct']);

        $generatedImage = trim((string) ($data['generatedImage'] ?? ''));
        $imageFile      = $request->files->get('quiz')['imageFile'] ?? null;

        if ($imageFile) {
            $fileName = uniqid() . '.' . $imageFile->guessExtension();
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/quiz',
                $fileName
            );
            $quiz->setImage($fileName);
        } elseif ($generatedImage !== '') {
            $quiz->setImage($generatedImage);
        }

        $em->flush();

        $this->addFlash('success', 'Quiz modifié avec succès.');

        return $this->redirectToRoute('app_formation_show_admin', [
            'id' => $quiz->getFormation()->getId(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_quiz_delete', methods: ['POST'])]
    public function delete(
        Quiz $quiz,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $formationId = $quiz->getFormation()->getId();
        if ($this->isCsrfTokenValid('delete_quiz_' . $quiz->getId(), (string) $request->request->get('_token'))) {
            $em->remove($quiz);
            $em->flush();
        }

        return $this->redirectToRoute('app_formation_show_admin', [
            'id' => $formationId,
        ]);
    }
}