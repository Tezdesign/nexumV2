<?php

namespace App\Controller\MobilePort;

use App\Entity\Dto\Auth\LoginInput;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Controller\Trait\ValidationFlashTrait;

class MobileAuthController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/mobile/login', name: 'app_mobile_login')]
    public function login(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToRoute('app_mobile_dashboard');
        }

        if ($request->isMethod('POST')) {
            $input = LoginInput::fromRequest($request);
            if ($this->flashValidationErrors($this->validator->validate($input))) {
                return $this->render('mobile/auth/login.html.twig');
            }

            $utilisateur = $this->authService->login($input->email, $input->password);

            if ($utilisateur) {
                return $this->redirectToRoute('app_mobile_dashboard');
            }

            $this->addFlash('error', 'Email ou mot de passe incorrect.');
        }

        return $this->render('mobile/auth/login.html.twig');
    }
}
