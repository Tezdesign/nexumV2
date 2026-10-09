<?php

namespace App\Controller;

use App\Attribute\RequireAdmin;
use App\Attribute\RequireLogin;
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
use App\Service\TranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/formation')]
#[RequireLogin]
final class FormationController extends AbstractController
{
    /** Progress the videos can award, in order: video 1, video 2, video 3 (the last one unlocks the quiz). */
    private const PROGRESS_MILESTONES = [33, 66, 90];
    private const QUIZ_UNLOCK_PROGRESS = 90;
    private const PASS_PERCENT = 60;
    /** Scored attempts allowed per formation in 24 hours. Without a limit the score shown after each try lets someone find the answers one by one. */
    private const MAX_ATTEMPTS_PER_DAY = 3;
  
    
    
    
    
    
    
    
    
    #[Route('/progress/update', name: 'app_progress_update', methods: ['POST'])]
    public function updateProgress(Request $request, EntityManagerInterface $em, \App\Service\AuthService $authService): JsonResponse
    {
        $currentUserId = (int) ($authService->getCurrentUserId() ?? 0);
        $user = $currentUserId > 0 ? $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find($currentUserId) : null;
        if ($user === null) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $formationId = $request->request->getInt('formationId');
        $requested = $request->request->getInt('progress');
        if ($formationId <= 0) {
            return new JsonResponse(['error' => 'formationId missing'], 400);
        }

        $formation = $em->getRepository(Formation::class)->find($formationId);
        if ($formation === null) {
            return new JsonResponse(['error' => 'Formation introuvable'], 404);
        }

        $participation = $em->getRepository(Participer::class)->findOneBy(['user' => $user, 'formation' => $formation]);
        if ($participation === null) {
            $participation = (new Participer())->setUser($user)->setFormation($formation)->setProgression(0);
            $em->persist($participation);
        }

        // The browser reports a finished video; the server decides what that is worth. Progress moves one
        // milestone at a time, in order, so a single request can never jump straight to "ready for the quiz".
        $current = (int) $participation->getProgression();
        foreach (self::PROGRESS_MILESTONES as $milestone) {
            if ($milestone > $current) {
                if ($requested >= $milestone) {
                    $participation->setProgression($milestone);
                    if ($milestone >= self::QUIZ_UNLOCK_PROGRESS && !in_array($participation->getStatut(), ['REUSSI', 'ECHEC'], true)) {
                        $participation->setStatut('PRET_QUIZ');
                    }
                }
                break;
            }
        }
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'progress' => $participation->getProgression()
        ]);
    }

    #[Route('/localisation/update', name: 'app_participation_localisation_update', methods: ['POST'])]
    public function updateParticipationLocalisation(Request $request, EntityManagerInterface $em, \App\Service\AuthService $authService): JsonResponse
    {
        $userId = (int) ($authService->getCurrentUserId() ?? 0);
        $user = $userId > 0 ? $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find($userId) : null;
        if (!$user) {
            return new JsonResponse(['error' => 'User not found or not authenticated'], 401);
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
        \App\Service\QrService $qrService,
        \App\Service\AuthService $authService,
        LoggerInterface $logger,
    ): JsonResponse {
        $userId = (int) ($authService->getCurrentUserId() ?? 0);
        $user = $userId > 0 ? $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find($userId) : null;
        if ($user === null) {
            return $this->json(['success' => false, 'error' => 'Utilisateur introuvable'], 401);
        }

        $data = json_decode((string) $request->getContent(), true);
        $formationId = is_array($data) ? (int) ($data['formationId'] ?? 0) : 0;
        $answers = is_array($data) && is_array($data['answers'] ?? null) ? $data['answers'] : [];
        if ($formationId <= 0 || $answers === []) {
            return $this->json(['success' => false, 'error' => 'Données invalides'], 400);
        }

        $formation = $em->getRepository(Formation::class)->find($formationId);
        if ($formation === null) {
            return $this->json(['success' => false, 'error' => 'Formation introuvable'], 404);
        }

        $participation = $em->getRepository(Participer::class)->findOneBy(['user' => $user, 'formation' => $formation]);
        if ($participation === null) {
            return $this->json(['success' => false, 'error' => 'Vous ne participez pas à cette formation.'], 404);
        }

        // The quiz opens when the last video is done (PRET_QUIZ). A failed attempt (ECHEC) may retry, and a passed one
        // (REUSSI) may ask for the certificate again.
        $alreadyPassed = $participation->getStatut() === 'REUSSI';
        if (!$alreadyPassed && !in_array($participation->getStatut(), ['PRET_QUIZ', 'ECHEC'], true)) {
            return $this->json(['success' => false, 'error' => 'Terminez d’abord toutes les vidéos avant de passer le quiz.'], 403);
        }

        if (!$alreadyPassed) {
            $recentAttempts = (int) $em->getRepository(Resultat::class)->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->andWhere('r.user = :user')
                ->andWhere('r.formation = :formation')
                ->andWhere('r.datePassage >= :since')
                ->setParameter('user', $user)
                ->setParameter('formation', $formation)
                ->setParameter('since', new \DateTime('-24 hours'))
                ->getQuery()
                ->getSingleScalarResult();
            if ($recentAttempts >= self::MAX_ATTEMPTS_PER_DAY) {
                return $this->json([
                    'success' => false,
                    'error' => 'Nombre maximum de tentatives atteint pour aujourd’hui. Réessayez dans 24 heures.',
                ], 429);
            }
        }

        // Only questions that have a correct choice can be graded (AI short answers have none).
        $quizzes = array_values(array_filter(
            $em->getRepository(Quiz::class)->findBy(['formation' => $formation]),
            static fn (Quiz $quiz): bool => $quiz->getCorrect() !== null
        ));
        $score = 0;
        foreach ($quizzes as $quiz) {
            if (isset($answers[$quiz->getId()]) && (string) $answers[$quiz->getId()] === (string) $quiz->getCorrect()) {
                ++$score;
            }
        }
        $total = count($quizzes);
        $percent = $total > 0 ? ($score / $total) * 100 : 0;

        if (!$alreadyPassed) {
            $em->persist((new Resultat())
                ->setFormation($formation)
                ->setUser($user)
                ->setScore($score)
                ->setTotal($total)
                ->setDatePassage(new \DateTime()));
        }

        $passed = $alreadyPassed || $percent >= self::PASS_PERCENT;
        $participation->setStatut($passed ? 'REUSSI' : 'ECHEC');
        $em->flush();

        $result = ['score' => $score, 'total' => $total, 'percent' => round($percent)];
        if (!$passed) {
            return $this->json(['success' => true, 'message' => 'Quiz non validé.'] + $result);
        }

        try {
            $this->buildCertificate($user, $formation, $certificateService, $qrService);
        } catch (\Throwable $e) {
            // The pass is already saved; the certificate can be asked for again. Details stay in the log.
            $logger->error('Certificate flow failed', ['user' => $user->getId(), 'formation' => $formation->getId(), 'exception' => $e]);

            return $this->json([
                'success' => false,
                'error' => $alreadyPassed
                    ? 'Quiz déjà validé, mais la réémission du certificat a échoué. Réessayez plus tard.'
                    : 'Quiz validé, mais la génération du certificat a échoué. Réessayez plus tard.',
            ] + $result, 500);
        }

        return $this->json([
            'success' => true,
            'message' => $alreadyPassed ? 'Quiz déjà validé, certificat disponible.' : 'Quiz validé, certificat disponible.',
        ] + $result);
    }

    /**
     * Writes the user's certificate PDF (outside public/) and returns its path.
     *
     * @throws \Throwable when the QR code or the PDF cannot be produced
     */
    private function buildCertificate(
        \App\Entity\UserHandling\Utilisateur $user,
        Formation $formation,
        CertificateService $certificateService,
        \App\Service\QrService $qrService,
    ): string {
        $certificateId = 'CERT-' . strtoupper(substr(md5($user->getId() . '|' . $formation->getId()), 0, 8));

        $qrPath = $qrService->generate(implode("\n", [
            'CERTIFICATE',
            'Name: ' . $user->getNom(),
            'Formation: ' . $formation->getTitre(),
            'Date: ' . date('d/m/Y'),
            'ID: ' . $certificateId,
            'Status: VALID',
        ]));

        try {
            $certificatePath = $certificateService->generate($user, $formation, $certificateId, $qrPath);
        } finally {
            // The QR image is only needed while the PDF is drawn.
            if ($qrPath !== null && is_file($qrPath)) {
                @unlink($qrPath);
            }
        }

        if ($certificatePath === '' || !is_file($certificatePath)) {
            throw new \RuntimeException('Certificate file missing after generation');
        }

        return $certificatePath;
    }

    /** The owner downloads their certificate. It is built again if the file is gone. */
    #[Route('/{id}/certificate', name: 'app_formation_certificate', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function certificate(
        Formation $formation,
        EntityManagerInterface $em,
        CertificateService $certificateService,
        \App\Service\QrService $qrService,
        \App\Service\AuthService $authService,
        LoggerInterface $logger,
    ): Response {
        $userId = (int) ($authService->getCurrentUserId() ?? 0);
        $user = $userId > 0 ? $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find($userId) : null;
        $participation = $user === null ? null : $em->getRepository(Participer::class)->findOneBy(['user' => $user, 'formation' => $formation]);
        if ($user === null || $participation === null || $participation->getStatut() !== 'REUSSI') {
            throw $this->createNotFoundException();
        }

        $path = $certificateService->pathFor($user, $formation);
        if (!is_file($path)) {
            try {
                $path = $this->buildCertificate($user, $formation, $certificateService, $qrService);
            } catch (\Throwable $e) {
                $logger->error('Certificate download failed', ['user' => $userId, 'formation' => $formation->getId(), 'exception' => $e]);
                $this->addFlash('error', 'Le certificat n’a pas pu être généré. Réessayez plus tard.');

                return $this->redirectToRoute('app_formation_show', ['id' => $formation->getId()]);
            }
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'certificat-' . $formation->getId() . '.pdf');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

#[Route(name: 'app_formation_index', methods: ['GET'])]
public function index(
    Request $request,
    FormationRepository $formationRepository,
    EntityManagerInterface $em,
    PaginatorInterface $paginator,
    \App\Service\AuthService $authService
): Response {

    if ($authService->isAdmin()) {
                return $this->redirectToRoute('app_formation_index_admin');
    }

    $userId = (int) ($authService->getCurrentUserId() ?? 0);
    $user = $userId > 0 ? $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find($userId) : null;
    if (!$user) {
        return $this->redirectToRoute('login'); // Safely redirect if not logged in
    }

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
    
    // The current user's own participation for each formation on the page (one query).
    $participations = [];
    foreach ($formations as $formation) {
        $participations[$formation->getId()] = null;
    }
    $own = $participations === [] ? [] : $em->getRepository(Participer::class)->findBy([
        'user' => $user,
        'formation' => iterator_to_array($formations),
    ]);
    foreach ($own as $participation) {
        $participations[$participation->getFormation()?->getId()] = $participation;
    }

    return $this->render('formation/index.html.twig', [
        'formations' => $formations,
        'participations' => $participations,
        'q' => $q,
        'ajax' => $request->isXmlHttpRequest(),
    ]);
}

#[Route( '/admin',   name: 'app_formation_index_admin', methods: ['GET'])]
#[RequireAdmin]
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
    #[RequireAdmin]
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

                // Text goes in as a string cell: a title starting with "=" must never become a formula.
                $sheet->setCellValue('A' . $row, $formation->getId());
                foreach (['B' => $formation->getTitre(), 'C' => $formation->getDescription(), 'D' => $formation->getVideo1(), 'E' => $formation->getVideo2(), 'F' => $formation->getVideo3()] as $column => $text) {
                    $sheet->setCellValueExplicit($column . $row, $sanitize($text), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

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
    #[RequireAdmin]
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
        
                $uploadDir = $this->getParameter('videos_directory');
            if (!is_string($uploadDir)) {
                throw new \RuntimeException('Invalid videos_directory parameter.');
            }
        
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
    public function show(Formation $formation, EntityManagerInterface $em, \App\Service\AuthService $authService): Response    {
        $userId = (int) ($authService->getCurrentUserId() ?? 0);
        $user = $userId > 0 ? $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find($userId) : null;
        
        if (!$user) {
            return $this->redirectToRoute('login'); // Safely redirect if not logged in
        }
    
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
        return $this->render('formation/show.html.twig', [
            'formation'=>$formation,
            'participation'=>$participation,
            'myRating' => $em->getRepository(\App\Entity\Rating::class)->findOneBy(['user' => $user, 'formation' => $formation])?->getValue(),
        ]);
    }

    #[Route('/{id}/admin/form', name: 'app_formation_show_admin', methods: ['GET'])]
    #[RequireAdmin]
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
    #[RequireAdmin]
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
            if (!is_string($uploadDir)) {
                throw new \RuntimeException('Invalid videos_directory parameter.');
            }

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
    #[RequireAdmin]
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
    public function resultats(EntityManagerInterface $em, \App\Service\AuthService $authService): Response
    {
        // Admins see every attempt; everyone else only their own.
        $qb = $em->getRepository(Resultat::class)
            ->createQueryBuilder('r')
            ->orderBy('r.datePassage', 'DESC');

        if (!$authService->isAdmin()) {
            $qb->andWhere('r.user = :userId')->setParameter('userId', (int) $authService->getCurrentUserId());
        }

        return $this->render('resultat/index.html.twig', [
            'resultats' => $qb->getQuery()->getResult()
        ]);
    }

    /** The description in another language. Only called when the user picks a language, and cached. */
    #[Route('/translate/{id}', name: 'app_translate', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function translate(
        Formation $formation,
        Request $request,
        TranslatorService $translator
    ): JsonResponse {
        $lang = (string) $request->query->get('lang', 'en');
        if (!TranslatorService::isSupported($lang)) {
            return new JsonResponse(['error' => 'Langue non supportée.'], 400);
        }

        return new JsonResponse([
            'translated' => $translator->translate((string) $formation->getDescription(), 'fr', $lang)
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