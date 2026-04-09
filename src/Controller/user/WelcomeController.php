<?php

namespace App\Controller\user;

use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\Dto\Auth\LoginInput;
use App\Entity\Dto\Auth\RegistrationInput;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WelcomeController extends AbstractController
{
    use ValidationFlashTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/welcome', name: 'welcome')]
    public function welcome(): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToRoute($this->authService->isAdmin() ? 'admin_home' : 'dashboard');
        }

        return $this->render('auth/welcome.html.twig');
    }

    #[Route('/login', name: 'login')]
    public function login(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToRoute($this->authService->isAdmin() ? 'admin_home' : 'dashboard');
        }

        if ($request->isMethod('POST')) {
            $input = LoginInput::fromRequest($request);
            if ($this->flashValidationErrors($this->validator->validate($input))) {
                return $this->render('auth/login.html.twig');
            }

            $utilisateur = $this->authService->login($input->email, $input->password);

            if ($utilisateur) {
                $this->addFlash('success', 'Connexion réussie !');

                return $this->redirectToRoute($this->authService->isAdmin() ? 'admin_home' : 'dashboard');
            }

            $this->addFlash('error', 'Email ou mot de passe incorrect.');
        }

        return $this->render('auth/login.html.twig');
    }

    #[Route('/signin', name: 'signin')]
    public function signin(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToRoute($this->authService->isAdmin() ? 'admin_home' : 'dashboard');
        }

        if ($request->isMethod('POST')) {
            try {
                $input = RegistrationInput::fromRequest($request);
                if ($this->flashValidationErrors($this->validator->validate($input))) {
                    return $this->render('auth/signin.html.twig');
                }

                $utilisateur = new Utilisateur();
                $utilisateur->setPrenom($input->prenom);
                $utilisateur->setNom($input->nom);
                $utilisateur->setEmail($input->email);
                $utilisateur->setTelephone($input->telephone !== '' ? $input->telephone : null);
                $utilisateur->setRole($input->role);
                $utilisateur->setDepartement($input->departement !== '' ? $input->departement : null);
                $utilisateur->setStatut('pending');
                $utilisateur->setScore(100);
                $utilisateur->setPassword($input->password);

                $imageFile = $request->files->get('imagelink');
                if ($imageFile) {
                    $imageData = file_get_contents($imageFile->getPathname());
                    $utilisateur->setImagelink($imageData);
                }

                if ($this->authService->register($utilisateur)) {
                    $this->addFlash('success', 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');

                    return $this->redirectToRoute('login');
                }

                $this->addFlash('error', 'Cet email est déjà utilisé.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création du compte.');
            }
        }

        return $this->render('auth/signin.html.twig');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): Response
    {
        $this->authService->logout();

        return $this->redirectToRoute('welcome');
    }

    #[Route('/clear-session', name: 'clear_session')]
    public function clearSession(): Response
    {
        session_destroy();
        $this->addFlash('success', 'Session effacée. Veuillez vous reconnecter.');

        return $this->redirectToRoute('welcome');
    }
}
