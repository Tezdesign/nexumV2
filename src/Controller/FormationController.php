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
use App\Service\QrService as ServiceQrService;
use App\Service\TranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
  
    










   
    
    
     #[Route('/quiz/submit', name: 'app_quiz_submit', methods: ['POST'])]
    public function submitQuiz(
        Request $request,
        EntityManagerInterface $em,
        CertificateService $certificateService,
        MailService $mailService,
        ServiceQrService $qrService,
        MailerInterface $mailer
    ): JsonResponse{
    // ⚠️ idéalement utiliser $this->getUser()
        $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);
     if (!$user) {
        return new JsonResponse(['error' => 'User not found'], 404);
    }

    $data = json_decode($request->getContent(), true);

    $formationId = $data['formationId'] ?? null;
    $answers = $data['answers'] ?? [];

    if (!$formationId || empty($answers)) {
        return new JsonResponse(['error' => 'Données invalides'], 400);
    }

    $formation = $em->getRepository(Formation::class)->find($formationId);

    if (!$formation) {
        return new JsonResponse(['error' => 'Formation introuvable'], 404);
    }

    // 🎯 récupérer quiz
    $quizzes = $em->getRepository(Quiz::class)
        ->findBy(['formation' => $formation]);

    $score = 0;

    foreach ($quizzes as $quiz) {
        if (
            isset($answers[$quiz->getId()]) &&
            $answers[$quiz->getId()] == $quiz->getCorrect()
        ) {
            $score++;
        }
    }

    $total = count($quizzes);

    // 🔍 vérifier si déjà réussi
    $participation = $em->getRepository(Participer::class)
        ->findOneBy(['user' => $user, 'formation' => $formation]);

    if (!$participation) {
        return new JsonResponse(['error' => 'Participation not found'], 404);
    }

    // 🚫 Déjà réussi → on ne recrée pas un résultat
    if ($participation->getStatut() === 'REUSSI') {



        return new JsonResponse([
            'score' => $score,
            'total' => $total,
            'message' => 'Quiz déjà validé'
        ]);
    }

    // 🎯 CALCUL STATUT
    $percent = ($total > 0) ? ($score / $total) * 100 : 0;
    $isSuccess = $percent >= 60;

    // 🧾 CREATION RESULTAT
    $resultat = new Resultat();
    $resultat->setFormation($formation);
    $resultat->setUser($user);
    $resultat->setScore($score);
    $resultat->setTotal($total);
    $resultat->setDatePassage(new \DateTime());

    $em->persist($resultat);

    // 🔄 UPDATE PARTICIPATION
    if ($isSuccess) {
        $participation->setStatut('REUSSI');

        $qrData = "
        CERTIFICATE
        
        Name: ".$user->getNom()."
        Formation: ".$formation->getTitre()."
        Date: ".date('d/m/Y')."
        ID: ". "CERT-ED446F5D" ."
        Status: VALID
        ";
        
        $qrPath = $qrService->generate($qrData);
         
        $certificatePath = $certificateService->generate(
            $user,
            $formation,
            $qrPath
        );

// 📧 envoyer mail
$mailService->sendCertificate($user, $formation, $certificatePath, $mailer);

    } else {
        $participation->setStatut('ECHEC');
    }

    $em->flush();
 
    return new JsonResponse([
        'score' => $score,
        'total' => $total,
        'success' => $isSuccess,
        'percent' => round($percent)
    ]);
}
    
    
    
#[Route(name: 'app_formation_index', methods: ['GET'])]
public function index(
    Request $request,
    FormationRepository $formationRepository,
    EntityManagerInterface $em,
    PaginatorInterface $paginator
): Response {
    // ⚠️ temporaire
    $user = $em->getRepository(\App\Entity\UserHandling\Utilisateur::class)->find(1);

    $q = trim((string) $request->query->get('q', ''));
    $page = $request->query->getInt('page', 1);

    $qb = $formationRepository->createQueryBuilder('f');

    if ($q !== '') {
        $qb->andWhere('f.titre LIKE :q OR f.description LIKE :q')
           ->setParameter('q', '%' . $q . '%');
    }

    $qb->orderBy('f.id', 'DESC');

    $formations = $paginator->paginate($qb, $page, 6);

    $participations = [];
    $formationIds = [];

    foreach ($formations as $formation) {
        $formationIds[] = $formation->getId();
    }

    if ($user && !empty($formationIds)) {
        $rows = $em->createQueryBuilder()
            ->select('p', 'f')
            ->from(Participer::class, 'p')
            ->join('p.formation', 'f')
            ->where('p.user = :user')
            ->andWhere('f.id IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $formationIds)
            ->getQuery()
            ->getResult();

        foreach ($rows as $p) {
            $participations[$p->getFormation()->getId()] = $p;
        }
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
        $formations = $paginator->paginate(
            $formationRepository->findAll(), // Query
            $request->query->getInt('page', 1), // page
            6 // limit par page (3x2 grid parfait)
        );
        // public/test.php
         return $this->render('formation/index_admin.html.twig', [
            'formations' => $formations,
        ]);
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
        
                $projectDir = $this->getParameter('kernel.project_dir');
                if (!is_string($projectDir)) {
                    throw new \RuntimeException('Invalid project directory parameter.');
                }
                $uploadDir = $projectDir . '/public/uploads/videos';
        
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
        
     $formation->setTitre($badWord->clean((string) $formation->getTitre()));
    $formation->setDescription($badWord->clean((string) $formation->getDescription()));
                $em->persist($formation);
                $em->flush();
        
                return $this->redirectToRoute('app_formation_index');
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
            (string) $formation->getDescription(),
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
            if (!is_string($uploadDir)) {
                throw new \RuntimeException('Invalid videos directory parameter.');
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

            return $this->redirectToRoute('app_formation_index', [], Response::HTTP_SEE_OTHER);
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

    private function uploadVideo(UploadedFile $videoFile, SluggerInterface $slugger, string $uploadDir): string
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
            (string) $formation->getDescription(),
            'fr',
            is_string($lang) ? $lang : 'en'
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

    if (is_string($q) && $q !== '') {
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
