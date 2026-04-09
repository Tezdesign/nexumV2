<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AvatarExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('avatar_placeholder', [$this, 'avatarPlaceholder']),
        ];
    }

    /**
     * Returns a deterministic "random" looking color + initial for placeholder avatars.
     *
     * @return array{initial: string, bg: string, fg: string}
     */
    public function avatarPlaceholder(?string $name): array
    {
        $name = trim((string) $name);
        $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';

        // Stable hash so the same user always gets the same color across requests.
        $hash = (int) sprintf('%u', crc32($name !== '' ? $name : 'nexum'));

        $palette = [
            ['bg' => '#EEF2FF', 'fg' => '#4338CA'], // indigo
            ['bg' => '#ECFDF3', 'fg' => '#027A48'], // green
            ['bg' => '#FFF7ED', 'fg' => '#9A3412'], // orange
            ['bg' => '#EFF6FF', 'fg' => '#1D4ED8'], // blue
            ['bg' => '#FDF2F8', 'fg' => '#9D174D'], // pink
            ['bg' => '#F5F3FF', 'fg' => '#6D28D9'], // violet
            ['bg' => '#F1F5F9', 'fg' => '#0F172A'], // slate
        ];

        $pick = $palette[$hash % count($palette)];

        return [
            'initial' => $initial,
            'bg' => $pick['bg'],
            'fg' => $pick['fg'],
        ];
    }
}

