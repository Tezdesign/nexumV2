<?php

namespace App\Controller\user;

use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\Dto\Auth\LoginInput;
use App\Entity\Dto\Auth\RegistrationInput;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AuthService;
use App\Service\Captcha\CaptchaImageService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WelcomeController extends AbstractController
{
    use ValidationFlashTrait;

    private const MAX_PROFILE_IMAGE_BYTES = 2 * 1024 * 1024;
    private const LOGIN_CAPTCHA_CODE_KEY = 'login_captcha_code';

    public function __construct(
        private readonly AuthService $authService,
        private readonly ValidatorInterface $validator,
        private readonly CaptchaImageService $captchaImageService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/welcome', name: 'welcome')]
    public function welcome(): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToHome();
        }

        return $this->render('auth/welcome.html.twig');
    }

    #[Route('/login', name: 'login')]
    public function login(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToHome();
        }

        if ($request->isMethod('POST')) {
            $input = LoginInput::fromRequest($request);
            if ($this->flashValidationErrors($this->validator->validate($input))) {
                $this->ensureLoginCaptcha($request);

                return $this->render('auth/login.html.twig');
            }

            if (!$this->isLoginCaptchaValid($request)) {
                $this->addFlash('error', 'Invalid CAPTCHA.');
                $this->regenerateLoginCaptcha($request);

                return $this->render('auth/login.html.twig');
            }

            $utilisateur = $this->authService->login($input->email, $input->password);

            if ($utilisateur) {
                $this->addFlash('success', 'Connexion réussie !');

                return $this->redirectToHome();
            }

            $this->addFlash('error', 'Email ou mot de passe incorrect.');
            $this->regenerateLoginCaptcha($request);
        }

        $this->ensureLoginCaptcha($request);

        return $this->render('auth/login.html.twig');
    }

    #[Route('/login/captcha', name: 'login_captcha', methods: ['GET'])]
    public function loginCaptcha(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $session = $request->getSession();
        if ($request->query->getBoolean('renew')) {
            $this->regenerateLoginCaptcha($request);
        } else {
            $this->ensureLoginCaptcha($request);
        }

        $code = (string) $session->get(self::LOGIN_CAPTCHA_CODE_KEY, '');
        if ($code === '') {
            $this->regenerateLoginCaptcha($request);
            $code = (string) $session->get(self::LOGIN_CAPTCHA_CODE_KEY, '');
        }

        try {
            $png = $this->captchaImageService->renderPng($code);
        } catch (\Throwable) {
            return new Response('Captcha unavailable.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    #[Route('/signin', name: 'signin')]
    public function signin(Request $request): Response
    {
        if ($this->authService->isLoggedIn()) {
            return $this->redirectToHome();
        }

        if ($request->isMethod('POST')) {
            $input = RegistrationInput::fromRequest($request);
            if ($this->flashValidationErrors($this->validator->validate($input))) {
                return $this->render('auth/signin.html.twig');
            }

            $image = $this->readProfileImage($request);
            if ($image === false) {
                $this->addFlash('error', 'The profile photo must be an image of 2 MB or less.');

                return $this->render('auth/signin.html.twig');
            }

            $utilisateur = (new Utilisateur())
                ->setPrenom($input->prenom)
                ->setNom($input->nom)
                ->setEmail($input->email)
                ->setTelephone($input->telephone !== '' ? $input->telephone : null)
                ->setRole($input->role)
                ->setDepartement($input->departement !== '' ? $input->departement : null)
                ->setPassword($input->password)
                ->setImagelink($image);

            try {
                if ($this->authService->register($utilisateur)) {
                    $this->addFlash('success', 'Compte créé avec succès ! Un administrateur doit activer votre compte avant votre première connexion.');

                    return $this->redirectToRoute('login');
                }

                $this->addFlash('error', 'Cet email est déjà utilisé.');
            } catch (\Throwable $e) {
                $this->logger->error('Sign up failed', ['exception' => $e]);
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

    /**
     * @return string|null|false the image bytes, null when no photo was sent, false when the upload is not an acceptable image
     */
    private function readProfileImage(Request $request): string|null|false
    {
        $file = $request->files->get('imagelink');
        if (!$file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (!$file->isValid() || $file->getSize() > self::MAX_PROFILE_IMAGE_BYTES
            || !str_starts_with((string) $file->getMimeType(), 'image/')) {
            return false;
        }

        $bytes = file_get_contents($file->getPathname());

        return $bytes === false ? false : $bytes;
    }

    private function redirectToHome(): Response
    {
        return $this->redirectToRoute($this->authService->isAdmin() ? 'admin_home' : 'dashboard');
    }

    private function ensureLoginCaptcha(Request $request): void
    {
        $session = $request->getSession();
        $code = (string) $session->get(self::LOGIN_CAPTCHA_CODE_KEY, '');
        if ($code === '') {
            $this->regenerateLoginCaptcha($request);
        }
    }

    private function regenerateLoginCaptcha(Request $request): void
    {
        $session = $request->getSession();
        $session->set(self::LOGIN_CAPTCHA_CODE_KEY, $this->captchaImageService->generateCode(5));
    }

    private function isLoginCaptchaValid(Request $request): bool
    {
        $session = $request->getSession();
        $expected = (string) $session->get(self::LOGIN_CAPTCHA_CODE_KEY, '');
        $raw = strtoupper(trim((string) $request->request->get('_captcha', '')));
        $raw = preg_replace('/\s+/', '', $raw) ?? '';

        if ($expected === '' || $raw === '') {
            return false;
        }

        return hash_equals(strtoupper($expected), $raw);
    }
}
