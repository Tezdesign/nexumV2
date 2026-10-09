<?php

namespace App\Service\Chat;

use App\Entity\UserHandling\Utilisateur;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the browser loads a user's picture from.
 *
 * Chat JSON used to embed every picture as a base64 data URI, once per message, which made a long
 * conversation tens of megabytes. A URL lets the browser fetch each picture once and cache it.
 */
final class UserAvatarUrl
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /** Null when the user has no picture, so the page shows its initials instead of a broken image. */
    public function for(?Utilisateur $user): ?string
    {
        $userId = $user?->getId();
        $image = $user?->getImagelink();
        if ($userId === null || $image === null || $image === '') {
            return null;
        }

        return $this->urlGenerator->generate('apps-chat-user-avatar', ['userId' => $userId]);
    }
}
