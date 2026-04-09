<?php

namespace App\Twig;

use App\Repository\UserHandling\UtilisateurRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Temporary "current user" provider.
 *
 * Until real authentication is wired, we pick a user from the DB whose role contains "employee".
 */
final class CurrentUserGlobals extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly UtilisateurRepository $utilisateurRepository)
    {
    }

    public function getGlobals(): array
    {
        $user = $this->utilisateurRepository->findFirstManagerOrFirst();


        return [
            'current_user' => $user,
        ];
    }
}
