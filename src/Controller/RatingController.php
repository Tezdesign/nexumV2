<?php

namespace App\Controller;

use App\Attribute\RequireLogin;
use App\Entity\Formation;
use App\Entity\Rating;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation')]
#[RequireLogin]
class RatingController extends AbstractController
{
    /** One rating per user and formation: rating again replaces the previous note. */
    #[Route('/{id}/rate', name: 'app_formation_rate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rate(
        Formation $formation,
        Request $request,
        EntityManagerInterface $em,
        AuthService $authService
    ): Response {
        $back = $authService->isAdmin() ? 'app_formation_show_admin' : 'app_formation_show';

        $value = $request->request->getInt('rating');
        if ($value < 1 || $value > 5) {
            $this->addFlash('error', 'Veuillez choisir une note valide entre 1 et 5.');

            return $this->redirectToRoute($back, ['id' => $formation->getId()]);
        }

        $user = $em->getRepository(Utilisateur::class)->find((int) $authService->getCurrentUserId());
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }

        $rating = $em->getRepository(Rating::class)->findOneBy(['user' => $user, 'formation' => $formation])
            ?? (new Rating())->setUser($user)->setFormation($formation);
        $rating->setValue($value);

        $em->persist($rating);
        $em->flush();

        $this->addFlash('success', 'Merci pour votre note !');

        return $this->redirectToRoute($back, ['id' => $formation->getId()]);
    }
}
