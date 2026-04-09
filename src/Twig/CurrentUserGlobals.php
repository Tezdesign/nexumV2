<?php

namespace App\Twig;

use App\Service\AuthService;
use App\Repository\UserHandling\UtilisateurRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Temporary "current user" provider backed by the authenticated session user.
 */
final class CurrentUserGlobals extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UtilisateurRepository $utilisateurRepository
    )
    {
    }

    public function getGlobals(): array
    {
        $userId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $user = $userId > 0 ? $this->utilisateurRepository->find($userId) : null;


        return [
            'current_user' => $user,
        ];
    }
}
