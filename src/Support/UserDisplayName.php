<?php

namespace App\Support;

use App\Entity\UserHandling\Utilisateur;

final class UserDisplayName
{
    public static function format(?Utilisateur $user, ?int $fallbackId = null): string
    {
        if ($user === null) {
            return 'Unknown user';
        }

        $fullName = trim((string) $user->getPrenom() . ' ' . (string) $user->getNom());
        if ($fullName !== '') {
            return $fullName;
        }



        return 'Unknown user';
    }
}
