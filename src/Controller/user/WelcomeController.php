<?php

namespace App\Controller\user;

use App\Controller\Trait\ValidationFlashTrait;
use App\Entity\Dto\Auth\LoginInput;
use App\Entity\Dto\Auth\RegistrationInput;
use App\Entity\UserHandling\Utilisateur;
use App\Service\AuthService;
use App\Service\Captcha\CaptchaImageService;
use App\Service\CompreFaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WelcomeController extends AbstractController
{
    use ValidationFlashTrait;

    private const LOGIN_CAPTCHA_CODE_KEY = 'login_captcha_code';

    public function __construct(
        private readonly AuthService $authService,
        private readonly ValidatorInterface $validator,
        private readonly CaptchaImageService $captchaImageService,
        private readonly CompreFaceService $compreFaceService,
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
                // Ensure session is saved first
                $session = $request->getSession();
                $session->save();
                
                // Add flash message after session is saved
                $this->addFlash('success', 'Connexion réussie !');

                return $this->redirectToRoute($this->authService->isAdmin() ? 'admin_home' : 'dashboard');
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

                // Handle face registration
                $faceData = $request->request->get('face_data');
                if (is_string($faceData) && $faceData !== '') {
                    // Convert base64 to image data
                    $cleanFaceData = preg_replace('/^data:image\/[a-z]+;base64,/', '', $faceData) ?? '';
                    $imageData = base64_decode($cleanFaceData);
                    if ($imageData) {
                        // Register face with CompreFace using email as subject identifier
                        $faceId = $this->compreFaceService->registerFace($imageData, $input->email);
                        if ($faceId) {
                            $utilisateur->setFaceId($faceId);
                            $this->addFlash('success', 'Face registered successfully!');
                        } else {
                            $this->addFlash('warning', 'Face registration failed, but account was created successfully.');
                        }
                    }
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

    #[Route('/face-authenticate', name: 'face_authenticate', methods: ['POST'])]
    public function faceAuthenticate(Request $request): JsonResponse
    {
        if ($this->authService->isLoggedIn()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Already logged in'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['face_data']) || empty($data['face_data'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No face data provided'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Convert base64 to image data
            $imageData = base64_decode(preg_replace('/^data:image\/[a-z]+;base64,/', '', $data['face_data']));
            if (!$imageData) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid face data format'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if CompreFace service is available
            try {
                error_log('Starting face recognition process...');
                $recognizedEmail = $this->compreFaceService->recognizeFace($imageData);
                error_log('Face recognition result: ' . ($recognizedEmail ? $recognizedEmail : 'null'));
            } catch (\Exception $e) {
                error_log('CompreFace service error: ' . $e->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Face recognition service is currently unavailable. Please use email and password to login.'
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
            
            if ($recognizedEmail) {
                error_log('Face recognized, looking up user with email: ' . $recognizedEmail);
                // Find user by email
                $utilisateur = $this->authService->getUtilisateurRepository()->findByEmail($recognizedEmail);
                error_log('User lookup result: ' . ($utilisateur ? 'found' : 'not found'));
                
                if ($utilisateur) {
                    error_log('User found, checking face_id: ' . ($utilisateur->getFaceId() ? 'exists' : 'null'));
                    if ($utilisateur->getFaceId()) {
                        // Authenticate the user
                        error_log('Authenticating user...');
                        $this->authService->refreshSessionUser($utilisateur);
                        $this->authService->authenticateSymfonyUser($utilisateur);
                        
                        // Ensure session is saved
                        $session = $request->getSession();
                        $session->save();
                        
                        error_log('User authenticated successfully');
                        return new JsonResponse([
                            'success' => true,
                            'message' => 'Face authentication successful',
                            'redirect_url' => $this->generateUrl($this->authService->isAdmin() ? 'admin_home' : 'dashboard')
                        ]);
                    } else {
                        error_log('User found but no face_id registered');
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'User found but face not registered. Please register your face first.'
                        ]);
                    }
                }
            } else {
                error_log('Face not recognized by CompreFace');
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Face not recognized. Please try again or use your email and password.'
                ]);
            }
            
            // This should not be reached, but add a fallback
            error_log('Unexpected flow in face authentication');
            return new JsonResponse([
                'success' => false,
                'message' => 'Unexpected error during face authentication. Please try again.'
            ]);
        } catch (\Exception $e) {
            // Log the actual error for debugging
            error_log('Face authentication error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Face recognition service is currently unavailable. Please try again later.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
