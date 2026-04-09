<?php

namespace App\Entity\Dto\Auth;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RegistrationInput
{
    #[Assert\NotBlank(message: 'First name is required.')]
    #[Assert\Length(max: 50, maxMessage: 'First name cannot exceed {{ limit }} characters.')]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Last name is required.')]
    #[Assert\Length(max: 50, maxMessage: 'Last name cannot exceed {{ limit }} characters.')]
    public string $nom = '';

    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(max: 150)]
    public string $email = '';

    #[Assert\Length(max: 30)]
    public string $telephone = '';

    #[Assert\Length(max: 100)]
    public string $departement = '';

    #[Assert\NotBlank(message: 'Please select a role.')]
    #[Assert\Choice(choices: ['employee', 'manager', 'admin', 'hr', 'finance'], message: 'Invalid role.')]
    public string $role = '';

    #[Assert\NotBlank(message: 'Password is required.')]
    #[Assert\Length(min: 6, max: 255, minMessage: 'Password must be at least {{ limit }} characters.')]
    public string $password = '';

    #[Assert\NotBlank(message: 'Please confirm your password.')]
    public string $confirmPassword = '';

    #[Assert\Callback]
    public function assertPasswordsMatch(ExecutionContextInterface $context): void
    {
        if ($this->password !== '' && $this->password !== $this->confirmPassword) {
            $context->buildViolation('Password and confirmation do not match.')
                ->atPath('confirmPassword')
                ->addViolation();
        }
    }

    public static function fromRequest(Request $request): self
    {
        $i = new self();
        $i->prenom = trim((string) $request->request->get('prenom', ''));
        $i->nom = trim((string) $request->request->get('nom', ''));
        $i->email = trim((string) $request->request->get('email', ''));
        $i->telephone = trim((string) $request->request->get('telephone', ''));
        $i->departement = trim((string) $request->request->get('departement', ''));
        $i->role = strtolower(trim((string) $request->request->get('role', '')));
        $i->password = (string) $request->request->get('_password', '');
        $i->confirmPassword = (string) $request->request->get('confirm_password', '');

        return $i;
    }
}

