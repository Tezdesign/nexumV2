<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Participer;
use App\Entity\Quiz;
use App\Entity\Resultat;
use App\Form\FormationEditType;
use App\Form\FormationType;
use App\Form\QuizType;
use App\Repository\FormationRepository;
use App\Service\BadWordService;
use App\Service\CertificateService;
use App\Service\MailService;
use App\Service\TranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/formation')]
final class FormationController extends AbstractController
{  
    
    
    
    
    
    
    
    
    #[Route('/progress/update', name: 'app_progress_update', methods: ['POST'])]
    public function updateProgress(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // 🔥 USER TEMPORAIRE (remplace plus tard)
        $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);
    
        $formationId = $request->request->get('formationId');
        $progress = (int)$request->request->get('progress');
    
        if (!$formationId) {
            return new JsonResponse(['error' => 'formationId missing'], 400);
        }
    
        $participation = $em->getRepository(Participer::class)
            ->findOneBy([
                'user' => $user,
                'formation' => $formationId
            ]);
    
        // 👉 AUTO CREATE participation si inexistante
        if (!$participation) {
            $formation = $em->getRepository(Formation::class)->find($formationId);
    
            $participation = new Participer();
            $participation->setUser($user);
            $participation->setFormation($formation);
            $participation->setProgression(0);
    
            $em->persist($participation);
        }
    
        // 🔥 UPDATE seulement si progression supérieure
        if ($progress > $participation->getProgression()) {
    
            $participation->setProgression($progress);
    
            if ($progress >= 90) {
                $participation->setStatut('PRET_QUIZ');
            }
    
            $em->flush();
        }
    
        return new JsonResponse([
            'success' => true,
            'progress' => $participation->getProgression()
        ]);
    }

    #[Route('/localisation/update', name: 'app_participation_localisation_update', methods: ['POST'])]
    public function updateParticipationLocalisation(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // ⚠️ temporaire
        $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $formationId = $request->request->get('formationId');
        $localisation = $request->request->get('localisation');

        if (!$formationId) {
            return new JsonResponse(['error' => 'formationId missing'], 400);
        }

        $formation = $em->getRepository(Formation::class)->find($formationId);
        if (!$formation) {
            return new JsonResponse(['error' => 'Formation introuvable'], 404);
        }

        $participation = $em->getRepository(Participer::class)->findOneBy([
            'user' => $user,
            'formation' => $formation,
        ]);

        if (!$participation) {
            $participation = new Participer();
            $participation->setUser($user);
            $participation->setFormation($formation);
            $participation->setProgression(0);
            $em->persist($participation);
        }

        $localisationStr = is_string($localisation) ? trim($localisation) : null;
        $participation->setLocalisation($localisationStr === '' ? null : $localisationStr);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'localisation' => $participation->getLocalisation(),
        ]);
    }
  
    










   
    
    
    #[Route('/quiz/submit', name: 'app_quiz_submit', methods: ['POST'])]
public function submitQuiz(
    Request $request,
    EntityManagerInterface $em,
    CertificateService $certificateService,
    MailService $mailService,
    MailerInterface $mailer,
    \App\Service\QrService $qrService
): JsonResponse {
    $debugInfo = [
        'entered_submitQuiz' => true,
        'user_found' => false,
        'formation_found' => false,
        'score_computed' => false,
        'already_passed' => false,
        'passed_threshold' => false,
        'certificate_id' => null,
        'qr_generated' => false,
        'qr_path' => null,
        'qr_public_url' => null,
        'qr_file_exists' => false,
        'certificate_path' => null,
        'certificate_exists' => false,
        'mail_service_called' => false,
        'mail_sent' => false,
        'mailer_class' => get_debug_type($mailer),
        'error' => null,
    ];

    try {
        error_log('[Quiz] ===== submitQuiz entered =====');
        error_log('[Quiz] Content-Type: ' . ($request->headers->get('Content-Type') ?? 'null'));
        error_log('[Quiz] Raw body length: ' . strlen((string) $request->getContent()));

        $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);
        if (!$user) {
            $debugInfo['error'] = 'User not found';
            error_log('[Quiz] ERROR: User not found');
            return $this->json([
                'success' => false,
                'error' => 'User not found',
                'debug' => $debugInfo,
            ], 404);
        }

        $debugInfo['user_found'] = true;
        error_log('[Quiz] User found: ID=' . $user->getId() . ', email=' . $user->getEmail());

        try {
            $data = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $debugInfo['error'] = 'Invalid JSON data: ' . $e->getMessage();
            error_log('[Quiz] ERROR: Invalid JSON data - ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Invalid JSON data: ' . $e->getMessage(),
                'debug' => $debugInfo,
            ], 400);
        }

        $formationId = isset($data['formationId']) ? (int) $data['formationId'] : 0;
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        if ($formationId <= 0 || $answers === []) {
            $debugInfo['error'] = 'Données invalides';
            error_log('[Quiz] ERROR: formationId missing/invalid or answers empty');

            return $this->json([
                'success' => false,
                'error' => 'Données invalides',
                'debug' => $debugInfo,
            ], 400);
        }

        $formation = $em->getRepository(Formation::class)->find($formationId);
        if (!$formation) {
            $debugInfo['error'] = 'Formation introuvable';
            error_log('[Quiz] ERROR: Formation not found for ID=' . $formationId);

            return $this->json([
                'success' => false,
                'error' => 'Formation introuvable',
                'debug' => $debugInfo,
            ], 404);
        }

        $debugInfo['formation_found'] = true;
        error_log('[Quiz] Formation found: ID=' . $formation->getId() . ', title=' . $formation->getTitre());

        $quizzes = $em->getRepository(Quiz::class)->findBy(['formation' => $formation]);

        $score = 0;
        foreach ($quizzes as $quiz) {
            if (
                isset($answers[$quiz->getId()]) &&
                (string) $answers[$quiz->getId()] === (string) $quiz->getCorrect()
            ) {
                $score++;
            }
        }

        $total = count($quizzes);
        $percent = $total > 0 ? ($score / $total) * 100 : 0;

        $debugInfo['score_computed'] = true;
        $debugInfo['passed_threshold'] = $percent >= 60;

        error_log('[Quiz] Score computed: ' . $score . '/' . $total . ' (' . round($percent) . '%)');
        error_log('[Quiz] Success threshold reached: ' . (($percent >= 60) ? 'YES' : 'NO'));

        $participation = $em->getRepository(Participer::class)->findOneBy([
            'user' => $user,
            'formation' => $formation,
        ]);

        if (!$participation) {
            $debugInfo['error'] = 'Participation not found';
            error_log('[Quiz] ERROR: Participation not found');

            return $this->json([
                'success' => false,
                'error' => 'Participation not found',
                'debug' => $debugInfo,
            ], 404);
        }

        $alreadyPassed = $participation->getStatut() === 'REUSSI';
        $debugInfo['already_passed'] = $alreadyPassed;

        error_log('[Quiz] Participation current status: ' . ($participation->getStatut() ?? 'null'));
        error_log('[Quiz] Already passed before this request: ' . ($alreadyPassed ? 'YES' : 'NO'));

        if (!$alreadyPassed) {
            $resultat = new Resultat();
            $resultat->setFormation($formation);
            $resultat->setUser($user);
            $resultat->setScore($score);
            $resultat->setTotal($total);
            $resultat->setDatePassage(new \DateTime());
            $em->persist($resultat);
            error_log('[Quiz] Resultat entity created and persisted');
        } else {
            error_log('[Quiz] Participation already REUSSI - certificate/mail flow will be retried');
        }

        $needsSuccessFlow = $alreadyPassed || ($percent >= 60);

        if ($needsSuccessFlow) {
            $participation->setStatut('REUSSI');

            $certificateId = 'CERT-' . strtoupper(substr(md5($user->getId() . '|' . $formation->getId()), 0, 8));
            $debugInfo['certificate_id'] = $certificateId;

            error_log('[Quiz] Success flow started');
            error_log('[Quiz] Certificate ID: ' . $certificateId);

            $qrData = implode("\n", [
                'CERTIFICATE',
                'Name: ' . $user->getNom(),
                'Formation: ' . $formation->getTitre(),
                'Date: ' . date('d/m/Y'),
                'ID: ' . $certificateId,
                'Status: VALID',
            ]);

            try {
                error_log('[Quiz] Calling QrService::generate()...');
                $qrAbsolutePath = $qrService->generate($qrData);

                $debugInfo['qr_path'] = $qrAbsolutePath;
                $debugInfo['qr_generated'] = $qrAbsolutePath !== null;
                $debugInfo['qr_file_exists'] = $qrAbsolutePath !== null && is_file($qrAbsolutePath);

                error_log('[Quiz] QR path generated: ' . ($qrAbsolutePath ?? 'null'));
                error_log('[Quiz] QR file exists: ' . ($debugInfo['qr_file_exists'] ? 'YES' : 'NO'));

                $qrPublicUrl = null;
                if ($debugInfo['qr_file_exists']) {
                    $qrPublicUrl = $qrService->getPublicUrl($qrAbsolutePath);
                }

                $debugInfo['qr_public_url'] = $qrPublicUrl;
                error_log('[Quiz] QR public URL: ' . ($qrPublicUrl ?? 'null'));

                error_log('[Quiz] Calling CertificateService::generate()...');
                $certificatePath = $certificateService->generate($user, $formation, $certificateId, $qrPublicUrl);

                $debugInfo['certificate_path'] = $certificatePath;
                $debugInfo['certificate_exists'] = is_string($certificatePath) && $certificatePath !== '' && is_file($certificatePath);

                error_log('[Quiz] Certificate path generated: ' . ($certificatePath ?: 'null'));
                error_log('[Quiz] Certificate file exists: ' . ($debugInfo['certificate_exists'] ? 'YES' : 'NO'));

                if (!$debugInfo['certificate_exists']) {
                    throw new \RuntimeException('Certificate file missing after generation');
                }

                $debugInfo['mail_service_called'] = true;
                error_log('[Quiz] Calling MailService::sendCertificate()...');
                $mailService->sendCertificate($user, $formation, $certificatePath, $mailer);

                $debugInfo['mail_sent'] = true;
                error_log('[Quiz] MailService::sendCertificate() completed successfully');
            } catch (\Throwable $flowError) {
                $debugInfo['error'] = get_class($flowError) . ': ' . $flowError->getMessage();

                error_log('[Quiz] ERROR in certificate/QR/mail flow: ' . $flowError->getMessage());
                error_log('[Quiz] Exception type: ' . get_class($flowError));
                error_log('[Quiz] Exception trace: ' . $flowError->getTraceAsString());
            }
        } else {
            $participation->setStatut('ECHEC');
            error_log('[Quiz] Success threshold not reached - status set to ECHEC');
        }

        $em->flush();
        error_log('[Quiz] EntityManager flush completed');

        if ($needsSuccessFlow && (!$debugInfo['certificate_exists'] || !$debugInfo['mail_sent'])) {
            $errorMessage = $debugInfo['error'] ?? 'Certificate/email flow failed';

            return $this->json([
                'success' => false,
                'error' => $errorMessage,
                'score' => $score,
                'total' => $total,
                'percent' => round($percent),
                'message' => $alreadyPassed
                    ? 'Quiz déjà validé, mais la réémission du certificat a échoué.'
                    : 'Quiz validé, mais la génération du certificat ou l’envoi du mail a échoué.',
                'debug' => $debugInfo,
            ], 500);
        }

        return $this->json([
            'success' => true,
            'score' => $score,
            'total' => $total,
            'percent' => round($percent),
            'message' => $needsSuccessFlow
                ? ($alreadyPassed ? 'Quiz déjà validé, certificat envoyé.' : 'Quiz validé, certificat envoyé.')
                : 'Quiz non validé.',
            'debug' => $debugInfo,
        ]);
    } catch (\Throwable $e) {
        $debugInfo['error'] = get_class($e) . ': ' . $e->getMessage();

        error_log('[Quiz] FATAL ERROR in submitQuiz: ' . $e->getMessage());
        error_log('[Quiz] Exception type: ' . get_class($e));
        error_log('[Quiz] Exception trace: ' . $e->getTraceAsString());

        return $this->json([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => $debugInfo,
        ], 500);
    }
}
    
    
    
#[Route(name: 'app_formation_index', methods: ['GET'])]
public function index(
    Request $request,
    FormationRepository $formationRepository,
    EntityManagerInterface $em,
    PaginatorInterface $paginator
): Response {


    if ($this->getUser()->getRole()=='admin') {
                return $this->redirectToRoute('app_formation_index_admin');
    }



    // ⚠️ temporaire
    $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);

    $q = trim((string) $request->query->get('q', ''));
    $page = $request->query->getInt('page', 1);

    $qb = $formationRepository->createQueryBuilder('f');

    if ($q !== '') {
        $qb->andWhere('f.titre LIKE :q OR f.description LIKE :q')
           ->setParameter('q', '%' . $q . '%');
    }

    $formations = $paginator->paginate(
        $qb, // Query
        $request->query->getInt('page', 1), // page
        8 // limit par page
    );
    
    $participations = [];
    foreach ($formations as $formation) {
        $participations[$formation->getId()] = $formation->getParticipations()->first();
    }

    return $this->render('formation/index.html.twig', [
        'formations' => $formations,
        'participations' => $participations,
        'q' => $q,
        'ajax' => $request->isXmlHttpRequest(),
    ]);
}

#[Route( '/admin',   name: 'app_formation_index_admin', methods: ['GET'])]
public function index_admin(FormationRepository $formationRepository,Request $request ,PaginatorInterface $paginator, EntityManagerInterface $em): Response
{
    try {
        $formations = $paginator->paginate(
            $formationRepository->findAll(), // Query
            $request->query->getInt('page', 1), // page
            6 // limit par page (3x2 grid parfait)
        );
    } catch (\Throwable $e) {
        $formations = $paginator->paginate(
            [], // fallback sans BD/driver PDO
            $request->query->getInt('page', 1),
            6
        );
            $formations = $paginator->paginate(
                $formationRepository->findAll(), // Query
                $request->query->getInt('page', 1), // page
                6 // limit par page (3x2 grid parfait)
            );
        } catch (\Throwable $e) {
            $formations = $paginator->paginate(
                [], // fallback sans BD/driver PDO
                $request->query->getInt('page', 1),
                6
            );

            $this->addFlash('warning', 'Base de données indisponible: affichage en mode hors ligne.');
        }
        // public/test.php
         return $this->render('formation/index_admin.html.twig', [
            'formations' => $formations,
        ]);
    }

    #[Route('/admin/export/xls', name: 'app_formation_export_xls', methods: ['GET'])]
    public function exportXls(FormationRepository $formationRepository): Response
    {
        try {
            $formations = $formationRepository->findBy([], ['id' => 'DESC']);
        } catch (\Throwable $e) {
            $formations = [];
        }

        $response = new StreamedResponse(function () use ($formations): void {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Formations');

            // En-têtes
            $headers = ['ID', 'Titre', 'Description', 'Video 1', 'Video 2', 'Video 3'];
            $sheet->fromArray($headers, null, 'A1');

            // Style pour les en-têtes
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ];
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            // Données
            $row = 2;
            foreach ($formations as $formation) {
                $sanitize = static function (?string $value): string {
                    $value = $value ?? '';
                    return str_replace(["\r\n", "\r", "\n", "\t"], ' ', trim($value));
                };

                $sheet->fromArray([
                    $formation->getId(),
                    $sanitize($formation->getTitre()),
                    $sanitize($formation->getDescription()),
                    $sanitize($formation->getVideo1()),
                    $sanitize($formation->getVideo2()),
                    $sanitize($formation->getVideo3()),
                ], null, 'A' . $row);

                // Style pour les données
                $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                ]);

                $row++;
            }

            // Auto-size colonnes
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        });

        $filename = 'formations_' . (new \DateTimeImmutable())->format('Ymd_His') . '.xlsx';
        $disposition = sprintf('attachment; filename="%s"', $filename);

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

 
    #[Route('/new', name: 'app_formation_new', methods: ['GET', 'POST'])]
  public function new(Request $request, EntityManagerInterface $em, BadWordService $badWord): Response    {
        $formation = new Formation();
        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {

            $video1File = $form->get('video1File')->getData();
            $video2File = $form->get('video2File')->getData();
            $video3File = $form->get('video3File')->getData();
        
            // 🚨 VALIDATION MANUELLE
            if (!$video1File or !$video2File or !$video3File) {
                $form->addError(new \Symfony\Component\Form\FormError(
                    'Vous devez ajouter les  vidéo.'
                ));            }
        
            if (($video1File || $video2File || $video3File) && $form->isValid()) {
        
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/videos';
        
                if ($video1File) {
                    $fileName = uniqid().'.'.$video1File->guessExtension();
                    $video1File->move($uploadDir, $fileName);
                    $formation->setVideo1($fileName);
                }
        
                if ($video2File) {
                    $fileName = uniqid().'.'.$video2File->guessExtension();
                    $video2File->move($uploadDir, $fileName);
                    $formation->setVideo2($fileName);
                }
        
                if ($video3File) {
                    $fileName = uniqid().'.'.$video3File->guessExtension();
                    $video3File->move($uploadDir, $fileName);
                    $formation->setVideo3($fileName);
                }
        
     $formation->setTitre($badWord->clean($formation->getTitre()));
    $formation->setDescription($badWord->clean($formation->getDescription()));
                $em->persist($formation);
                $em->flush();
        
                return $this->redirectToRoute('app_formation_index_admin');
            }
        }
    
        return $this->render('formation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{id}', name: 'app_formation_show', methods: ['GET'])]
    public function show(Formation $formation, EntityManagerInterface $em, TranslatorService $translator): Response    {
        $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);
    
        $participation = $em->getRepository(Participer::class)
            ->findOneBy(['user'=>$user,'formation'=>$formation]);
    
        if (!$participation) {
            $participation = new Participer();
            $participation->setUser($user);
            $participation->setFormation($formation);
            $participation->setProgression(0);
            $participation->setStatut('PARTICIPER');
    
            $em->persist($participation);
            $em->flush();
        }
        $translated = $translator->translate(
            $formation->getDescription(),
            'fr',
            'en'
        );
        return $this->render('formation/show.html.twig', [
            'formation'=>$formation,
            'participation'=>$participation,
            'translated'=>$translated
        ]);
    }

    #[Route('/{id}/admin/form', name: 'app_formation_show_admin', methods: ['GET'])]
    public function show_admin(Formation $formation): Response
    {
        $quiz = new Quiz();
        $quiz->setFormation($formation);
    
        $quizForm = $this->createForm(QuizType::class, $quiz);
    
        return $this->render('formation/show_admin.html.twig', [
            'formation' => $formation,
            'quizForm' => $quizForm->createView(),
        ]);
    }
    #[Route('/{id}/edit', name: 'app_formation_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Formation $formation,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(FormationEditType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $video1File = $form->get('video1File')->getData();
            $video2File = $form->get('video2File')->getData();
            $video3File = $form->get('video3File')->getData();

            $uploadDir = $this->getParameter('videos_directory');

            if ($video1File) {
                $newFilename = $this->uploadVideo($video1File, $slugger, $uploadDir);
                $formation->setVideo1($newFilename);
            }

            if ($video2File) {
                $newFilename = $this->uploadVideo($video2File, $slugger, $uploadDir);
                $formation->setVideo2($newFilename);
            }

            if ($video3File) {
                $newFilename = $this->uploadVideo($video3File, $slugger, $uploadDir);
                $formation->setVideo3($newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Formation modifiée avec succès.');

            return $this->redirectToRoute('app_formation_index_admin', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('formation/edit.html.twig', [
            'formation' => $formation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_formation_delete', methods: ['POST'])]
    public function delete(Request $request, Formation $formation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $formation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($formation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
    }

    private function uploadVideo($videoFile, SluggerInterface $slugger, string $uploadDir): string
    {
        $originalFilename = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $videoFile->guessExtension();

        try {
            $videoFile->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors de l’upload de la vidéo.');
        }

        return $newFilename;
    }









     #[Route('/resultats/formations/quiz', name: 'app_resultats')]
    public function resultats(EntityManagerInterface $em): Response
    {
        $user = $this->getUser(); // ✅ mieux que find(1)
    
      //  $resultats = $em->getRepository(Resultat::class)
      //      ->findBy(['user' => $user], ['datePassage' => 'DESC']);
    
      $resultats = $em->getRepository(Resultat::class)
            ->createQueryBuilder('r')
 
            ->orderBy('r.datePassage', 'DESC')
            ->getQuery()
            ->getResult();
        return $this->render('resultat/index.html.twig', [
            'resultats' => $resultats
        ]);
    }





    #[Route('/translate/{id}', name: 'app_translate', methods: ['GET'])]
    public function translate(
        Formation $formation,
        Request $request,
        TranslatorService $translator
    ): JsonResponse {
    
        $lang = $request->query->get('lang', 'en');
    
        $translated = $translator->translate(
            $formation->getDescription(),
            'fr',
            $lang
        );
    
        return new JsonResponse([
            'translated' => $translated
        ]);
    }










 

#[Route('/api/formations', name: 'api_formations', methods: ['GET'])]
public function apiFormations(Request $request, FormationRepository $repo): JsonResponse
{
    $q = $request->query->get('q', '');

    $qb = $repo->createQueryBuilder('f');

    if (!empty($q)) {
        $qb->andWhere('f.titre LIKE :q OR f.description LIKE :q')
           ->setParameter('q', '%' . $q . '%');
    }

    $formations = $qb->orderBy('f.id', 'DESC')->getQuery()->getResult();

     $data = [];

    foreach ($formations as $f) {
        $data[] = [
            'id' => $f->getId(),
            'titre' => $f->getTitre(),
            'description' => $f->getDescription(),
            'video1' => $f->getVideo1(),
            'video2' => $f->getVideo2(),
            'video3' => $f->getVideo3(),
        ];
    }

    return $this->json($data);
}
}