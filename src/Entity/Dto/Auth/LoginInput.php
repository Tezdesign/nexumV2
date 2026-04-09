<?php

namespace App\Entity\Dto\Auth;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

final class LoginInput
{
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Password is required.')]
    public string $password = '';

    public static function fromRequest(Request $request): self
    {
        $i = new self();
        $i->email = trim((string) $request->request->get('_username', ''));
        $i->password = (string) $request->request->get('_password', '');

        return $i;
    }
}

