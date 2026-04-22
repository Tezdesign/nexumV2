<?php

namespace App\Controller;

use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AdminMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/password-reset')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly AdminMailService $adminMailService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    #[Route('/forgot', name: 'password_forgot', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            
            if ($email === '') {
                $this->addFlash('error', 'Please enter your email address.');
                return $this->redirectToRoute('password_forgot');
            }

            $user = $this->utilisateurRepository->findByEmail($email);
            if (!$user) {
                $this->addFlash('error', 'No account found with this email address.');
                return $this->redirectToRoute('password_forgot');
            }

            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store code in session with expiration (10 minutes)
            $session = $this->requestStack->getSession();
            $session->set('reset_code', $code);
            $session->set('reset_email', $email);
            $session->set('reset_code_time', time());

            try {
                $this->adminMailService->sendPasswordResetCode($email, $code);
                $this->addFlash('success', 'A verification code has been sent to your email.');
                return $this->redirectToRoute('password_verify');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Failed to send verification code. Please try again.');
                return $this->redirectToRoute('password_forgot');
            }
        }

        return $this->render('auth/forgot-password.html.twig');
    }

    #[Route('/verify', name: 'password_verify', methods: ['GET', 'POST'])]
    public function verifyCode(Request $request): Response
    {
        $session = $this->requestStack->getSession();
        $resetEmail = $session->get('reset_email');
        $resetCodeTime = $session->get('reset_code_time');

        // Check if session is valid (10 minutes)
        if (!$resetEmail || !$resetCodeTime || (time() - $resetCodeTime) > 600) {
            $this->addFlash('error', 'Your verification session has expired. Please try again.');
            return $this->redirectToRoute('password_forgot');
        }

        if ($request->isMethod('POST')) {
            $submittedCode = trim((string) $request->request->get('code', ''));
            $storedCode = $session->get('reset_code');

            if ($submittedCode === $storedCode) {
                // Clear the code and mark as verified
                $session->remove('reset_code');
                $session->set('reset_verified', true);
                return $this->redirectToRoute('password_reset');
            } else {
                $this->addFlash('error', 'Invalid verification code. Please try again.');
            }
        }

        return $this->render('auth/verify-code.html.twig', [
            'email' => $resetEmail
        ]);
    }

    #[Route('/resend', name: 'password_resend', methods: ['POST'])]
    public function resendCode(): JsonResponse
    {
        $session = $this->requestStack->getSession();
        $resetEmail = $session->get('reset_email');
        
        if (!$resetEmail) {
            return new JsonResponse(['success' => false, 'message' => 'Session expired. Please start over.']);
        }

        $user = $this->utilisateurRepository->findByEmail($resetEmail);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found.']);
        }

        // Generate new code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Update session
        $session->set('reset_code', $code);
        $session->set('reset_code_time', time());

        try {
            $this->adminMailService->sendPasswordResetCode($resetEmail, $code);
            return new JsonResponse(['success' => true, 'message' => 'Code resent successfully.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to resend code.']);
        }
    }

    #[Route('/reset', name: 'password_reset', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request): Response
    {
        $session = $this->requestStack->getSession();
        // Check if user is verified
        if (!$session->get('reset_verified') || !$session->get('reset_email')) {
            $this->addFlash('error', 'Access denied. Please complete verification first.');
            return $this->redirectToRoute('password_forgot');
        }

        if ($request->isMethod('POST')) {
            $password = trim((string) $request->request->get('password', ''));
            $confirmPassword = trim((string) $request->request->get('confirm_password', ''));

            if ($password === '') {
                $this->addFlash('error', 'Please enter a new password.');
                return $this->redirectToRoute('password_reset');
            }

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters long.');
                return $this->redirectToRoute('password_reset');
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('password_reset');
            }

            $email = $session->get('reset_email');
            $user = $this->utilisateurRepository->findByEmail($email);
            
            if ($user) {
                $user->setPassword($password);
                $this->entityManager->flush();
            }

            // Clear all reset session data
            $session->remove('reset_email');
            $session->remove('reset_verified');
            $session->remove('reset_code_time');

            $this->addFlash('success', 'Password reset successfully. Please login with your new password.');
            return $this->redirectToRoute('login');
        }

        return $this->render('auth/reset-password.html.twig');
    }
}
