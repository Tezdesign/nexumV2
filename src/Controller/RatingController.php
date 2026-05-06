<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Rating;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/formation')]
class RatingController extends AbstractController
{
    #[Route('/{id}/rate', name: 'app_formation_rate', methods: ['POST'])]
    public function rate(
        Formation $formation,
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $ratingValue = $request->request->get('rating');
        
        // Validate rating value
        $constraint = new Assert\Range([
            'min' => 1,
            'max' => 5,
            'notInRangeMessage' => 'La note doit être entre 1 et 5.'
        ]);
        
        $violations = $validator->validate($ratingValue, $constraint);
        
        if (count($violations) > 0) {
            $this->addFlash('error', 'Veuillez choisir une note valide entre 1 et 5.');
            return $this->redirectToRoute('app_formation_show_admin', ['id' => $formation->getId()]);
        }
        
        // Create and save rating
        $rating = new Rating();
        $rating->setValue((int) $ratingValue);
        $rating->setFormation($formation);
        
        $em->persist($rating);
        $em->flush();
        
        $this->addFlash('success', 'Merci pour votre note !');
        
        return $this->redirectToRoute('app_formation_show_admin', ['id' => $formation->getId()]);
    }
}
