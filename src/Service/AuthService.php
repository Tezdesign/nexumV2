<?php

namespace App\Service;

use App\Entity\UserHandling\Utilisateur;
use App\Repository\UserHandling\UtilisateurRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly RequestStack $requestStack,
        private readonly UserPasswordHasherInterface $passwordHasher,
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
                $utilisateur->setStatut('Pending');
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
        $session->invalidate();
    }

    public function isLoggedIn(): bool
    {
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
        $user = $this->getCurrentUser();
        return $user ? $user['id'] : null;
    }

    public function getCurrentUserRole(): ?string
    {
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
}
