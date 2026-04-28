<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Quiz;
use App\Form\QuizType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/quiz')]
final class QuizController extends AbstractController
{
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

        $imageFile = $form->get('imageFile')->getData();

        if ($imageFile instanceof UploadedFile) {
            $fileName = uniqid().'.'.$imageFile->guessExtension();
            $projectDir = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDir)) {
                throw new \RuntimeException('Invalid project directory parameter.');
            }
            $imageFile->move($projectDir.'/public/uploads/quiz', $fileName);
            $quiz->setImage($fileName);
        }

        $em->persist($quiz);
        $em->flush();

        $this->addFlash('success', 'Quiz ajouté avec image.');
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

    $data = $request->request->all('quiz');

    $quiz->setQuestion($data['question']);
    $quiz->setR1($data['r1']);
    $quiz->setR2($data['r2']);
    $quiz->setR3($data['r3']);
    $quiz->setCorrect((int)$data['correct']);

    // 📸 IMAGE
    $quizFiles = $request->files->get('quiz');
    $imageFile = is_array($quizFiles) ? ($quizFiles['imageFile'] ?? null) : null;

    if ($imageFile instanceof UploadedFile) {
        $fileName = uniqid().'.'.$imageFile->guessExtension();
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            throw new \RuntimeException('Invalid project directory parameter.');
        }
        $imageFile->move($projectDir.'/public/uploads/quiz', $fileName);
        $quiz->setImage($fileName);
    }

    $em->flush();

    return $this->redirectToRoute('app_formation_show_admin', [
        'id' => $quiz->getFormation()?->getId(),
    ]);
}


#[Route('/{id}/delete', name: 'app_quiz_delete', methods: ['POST'])]
    public function delete(
        Quiz $quiz,
        EntityManagerInterface $em
    ): Response {
        $formationId = $quiz->getFormation()?->getId();
        $em->remove($quiz);
        $em->flush();

        return $this->redirectToRoute('app_formation_show_admin', [
            'id' => $formationId,
        ]);
    }
}
