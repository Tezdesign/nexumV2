<?php

namespace App\Service;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthService
{
    private const FIREWALL_NAME = 'main';

    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly RequestStack $requestStack,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }

    public function login(string $email, string $password): ?Utilisateur
    {
        $utilisateur = $this->utilisateurRepository->login($email, $password);
        
        if ($utilisateur) {
            $this->refreshSessionUser($utilisateur);
            $this->authenticateSymfonyUser($utilisateur);
            
            return $utilisateur;
        }
        
        return null;
    }

    public function register(Utilisateur $utilisateur): bool
    {
        try {
            // Vérifier si l'email existe déjà
            if ($this->utilisateurRepository->findByEmail($utilisateur->getEmail())) {
                return false;
            }

            // Définir la date d'inscription et le statut par défaut
            $utilisateur->setDateInscription(new \DateTime());
            if (!$utilisateur->getStatut()) {
                $utilisateur->setStatut('pending');
            }
            if ($utilisateur->getScore() === 0) {
                $utilisateur->setScore(100);
            }
            
            // Sauvegarder en base de données
            $this->utilisateurRepository->create($utilisateur);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function logout(): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }
        $session->remove('user');
        $session->remove('_security_' . self::FIREWALL_NAME);
        $this->tokenStorage->setToken(null);
        $session->invalidate();
    }

    public function isLoggedIn(): bool
    {
        $tokenUser = $this->tokenStorage->getToken()?->getUser();
        if ($tokenUser instanceof Utilisateur) {
            return true;
        }

        $session = $this->session();

        return $session !== null && $session->has('user');
    }

    public function getCurrentUser(): ?array
    {
        $session = $this->session();
        if ($session === null) {
            return null;
        }

        return $session->get('user');
    }

    public function refreshSessionUser(Utilisateur $utilisateur, bool $profilePhotoUpdated = false): void
    {
        $session = $this->session();
        if ($session === null) {
            return;
        }
        $prev = $session->get('user');
        $avatarV = \is_array($prev) ? (int) ($prev['avatar_v'] ?? 0) : 0;
        if ($profilePhotoUpdated) {
            $avatarV = time();
        }
        $session->set('user', [
            'id' => $utilisateur->getId(),
            'email' => $utilisateur->getEmail(),
            'nom' => $utilisateur->getNom(),
            'prenom' => $utilisateur->getPrenom(),
            'role' => strtolower(trim((string) ($utilisateur->getRole() ?? ''))),
            'departement' => $utilisateur->getDepartement(),
            'statut' => strtolower(trim((string) ($utilisateur->getStatut() ?? ''))),
            'telephone' => $utilisateur->getTelephone(),
            'date_inscription' => $utilisateur->getDateInscription()?->format('Y-m-d'),
            'avatar_v' => $avatarV,
        ]);
    }

    public function getCurrentUserId(): ?int
    {
        $tokenUser = $this->tokenStorage->getToken()?->getUser();
        if ($tokenUser instanceof Utilisateur) {
            return $tokenUser->getId();
        }

        $user = $this->getCurrentUser();
        return $user ? $user['id'] : null;
    }

    public function getCurrentUserRole(): ?string
    {
        $tokenUser = $this->tokenStorage->getToken()?->getUser();
        if ($tokenUser instanceof Utilisateur) {
            return strtolower(trim((string) $tokenUser->getRole()));
        }

        $user = $this->getCurrentUser();
        return $user ? $user['role'] : null;
    }

    public function hasRole(string $role): bool
    {
        $current = $this->getCurrentUserRole();
        if ($current === null || $current === '') {
            return false;
        }

        $current = strtolower(trim($current));
        $role = strtolower(trim($role));

        return $current === $role || str_contains($current, $role);
    }

    public function isAdmin(): bool
    {
        $r = strtolower(trim((string) ($this->getCurrentUserRole() ?? '')));

        return $r === 'admin' || $r === 'administrator' || str_contains($r, 'admin');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager') || $this->isAdmin();
    }

    public function getUtilisateurRepository(): UtilisateurRepository
    {
        return $this->utilisateurRepository;
    }

    public function authenticateSymfonyUser(Utilisateur $utilisateur): void
    {
        $token = new UsernamePasswordToken($utilisateur, self::FIREWALL_NAME, $utilisateur->getRoles());
        $this->tokenStorage->setToken($token);

        $session = $this->session();
        if ($session === null) {
            return;
        }

        $session->set('_security_' . self::FIREWALL_NAME, serialize($token));
    }
}
