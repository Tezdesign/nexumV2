<?php

namespace App\Controller;

use App\Repository\UserHandling\UtilisateurRepository;
use App\Service\AdminMailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/password-reset')]
class PasswordResetController extends AbstractController
{
    private const CODE_TTL = 600;
    private const RESEND_COOLDOWN = 30;
    private const MAX_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW = 900;
    private const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly AdminMailService $adminMailService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
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

            $session = $request->getSession();
            $session->set('reset_email', $email);
            $session->remove('reset_verified');

            // Same answer whether or not the account exists, so the form cannot be used to list accounts.
            if ($this->utilisateurRepository->findByEmail($email) !== null && !$this->sendCode($session, $email)) {
                $this->addFlash('error', 'Failed to send verification code. Please try again.');

                return $this->redirectToRoute('password_forgot');
            }

            $this->addFlash('success', 'If an account exists for this email, a verification code has been sent.');

            return $this->redirectToRoute('password_verify');
        }

        return $this->render('auth/forgot-password.html.twig');
    }

    #[Route('/verify', name: 'password_verify', methods: ['GET', 'POST'])]
    public function verifyCode(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get('reset_email');
        if (!is_string($email) || $email === '') {
            return $this->restart($session, 'Your verification session has expired. Please try again.');
        }

        if ($request->isMethod('POST')) {
            if ($this->attemptsExhausted($email)) {
                return $this->restart($session, 'Too many attempts. Please try again later.');
            }

            $stored = $session->get('reset_code');
            $codeAge = time() - (int) $session->get('reset_code_time', 0);
            $submitted = trim((string) $request->request->get('code', ''));

            if (is_string($stored) && $codeAge <= self::CODE_TTL && hash_equals($stored, $submitted)) {
                $session->remove('reset_code');
                $session->set('reset_verified', time());

                return $this->redirectToRoute('password_reset');
            }

            $this->addFlash('error', 'Invalid or expired verification code. Please try again.');
        }

        return $this->render('auth/verify-code.html.twig', ['email' => $email]);
    }

    #[Route('/resend', name: 'password_resend', methods: ['POST'])]
    public function resendCode(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $email = $session->get('reset_email');
        if (!is_string($email) || $email === '') {
            return new JsonResponse(['success' => false, 'message' => 'Session expired. Please start over.']);
        }

        if (time() - (int) $session->get('reset_code_time', 0) < self::RESEND_COOLDOWN) {
            return new JsonResponse(['success' => false, 'message' => 'Please wait a few seconds before requesting a new code.']);
        }

        if ($this->utilisateurRepository->findByEmail($email) !== null && !$this->sendCode($session, $email)) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to resend code.']);
        }

        // Still stamp the time for unknown emails so the cooldown applies equally.
        $session->set('reset_code_time', time());

        return new JsonResponse(['success' => true, 'message' => 'Code resent successfully.']);
    }

    #[Route('/reset', name: 'password_reset', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get('reset_email');
        $verifiedAt = (int) $session->get('reset_verified', 0);

        if (!is_string($email) || $verifiedAt === 0 || time() - $verifiedAt > self::CODE_TTL) {
            return $this->restart($session, 'Access denied. Please complete verification first.');
        }

        if ($request->isMethod('POST')) {
            $password = trim((string) $request->request->get('password', ''));
            $confirm = trim((string) $request->request->get('confirm_password', ''));

            $error = match (true) {
                $password === '' => 'Please enter a new password.',
                strlen($password) < self::MIN_PASSWORD_LENGTH => sprintf('Password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH),
                $password !== $confirm => 'Passwords do not match.',
                default => null,
            };
            if ($error !== null) {
                $this->addFlash('error', $error);

                return $this->redirectToRoute('password_reset');
            }

            $user = $this->utilisateurRepository->findByEmail($email);
            if ($user !== null) {
                $user->setPassword($password);
                $this->entityManager->flush();
            }

            foreach (['reset_email', 'reset_code', 'reset_code_time', 'reset_verified'] as $key) {
                $session->remove($key);
            }
            $this->cache->delete($this->attemptsKey($email));

            $this->addFlash('success', 'Password reset successfully. Please login with your new password.');

            return $this->redirectToRoute('login');
        }

        return $this->render('auth/reset-password.html.twig');
    }

    private function sendCode(SessionInterface $session, string $email): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $session->set('reset_code', $code);
        $session->set('reset_code_time', time());

        try {
            $this->adminMailService->sendPasswordResetCode($email, $code);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Password reset email failed', ['exception' => $e]);

            return false;
        }
    }

    /** Counts a failed guess per email (not per session, so dropping the cookie does not reset it). */
    private function attemptsExhausted(string $email): bool
    {
        $key = $this->attemptsKey($email);
        /** @var int $count */
        $count = $this->cache->get($key, function (ItemInterface $item): int {
            $item->expiresAfter(self::ATTEMPT_WINDOW);

            return 0;
        });

        if ($count >= self::MAX_ATTEMPTS) {
            return true;
        }

        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($count): int {
            $item->expiresAfter(self::ATTEMPT_WINDOW);

            return $count + 1;
        });

        return false;
    }

    private function attemptsKey(string $email): string
    {
        return 'pwd_reset_attempts_' . sha1(strtolower($email));
    }

    private function restart(SessionInterface $session, string $message): Response
    {
        foreach (['reset_email', 'reset_code', 'reset_code_time', 'reset_verified'] as $key) {
            $session->remove($key);
        }
        $this->addFlash('error', $message);

        return $this->redirectToRoute('password_forgot');
    }
}
