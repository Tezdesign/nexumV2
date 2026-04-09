<?php

namespace App\Entity\Dto\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class AdminUserWriteInput
{
    public bool $isCreate = true;

    #[Assert\NotBlank(message: 'First name is required.')]
    #[Assert\Length(max: 50)]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Last name is required.')]
    #[Assert\Length(max: 50)]
    public string $nom = '';

    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(max: 150)]
    public string $email = '';

    #[Assert\Length(max: 20)]
    public string $telephone = '';

    #[Assert\Length(max: 100)]
    public string $departement = '';

    #[Assert\NotBlank(message: 'Role is required.')]
    #[Assert\Choice(choices: ['employee', 'manager', 'admin', 'hr', 'finance'], message: 'Invalid role.')]
    public string $role = '';

    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(choices: ['active', 'pending'], message: 'Invalid status.')]
    public string $statut = 'active';

    public string $password = '';

    public string $confirmPassword = '';

    #[Assert\Callback]
    public function validatePasswordRules(ExecutionContextInterface $context): void
    {
        if ($this->isCreate) {
            if ($this->password === '' || strlen($this->password) < 6) {
                $context->buildViolation('Password is required and must be at least 6 characters.')
                    ->atPath('password')
                    ->addViolation();
            }
            if ($this->password !== $this->confirmPassword) {
                $context->buildViolation('Password and confirmation do not match.')
                    ->atPath('confirmPassword')
                    ->addViolation();
            }
        } else {
            if ($this->password !== '' && strlen($this->password) < 6) {
                $context->buildViolation('New password must be at least 6 characters.')
                    ->atPath('password')
                    ->addViolation();
            }
            if ($this->password !== '' && $this->password !== $this->confirmPassword) {
                $context->buildViolation('Password and confirmation do not match.')
                    ->atPath('confirmPassword')
                    ->addViolation();
            }
        }
    }

    public static function fromRequest(Request $request, bool $isCreate): self
    {
        $i = new self();
        $i->isCreate = $isCreate;
        $i->prenom = trim((string) $request->request->get('prenom', ''));
        $i->nom = trim((string) $request->request->get('nom', ''));
        $i->email = trim((string) $request->request->get('email', ''));
        $i->telephone = trim((string) $request->request->get('telephone', ''));
        $i->departement = trim((string) $request->request->get('departement', ''));
        $i->role = strtolower(trim((string) $request->request->get('role', '')));
        $i->statut = strtolower(trim((string) $request->request->get('statut', 'active')));
        $i->password = (string) $request->request->get('_password', '');
        $i->confirmPassword = (string) $request->request->get('confirm_password', '');

        return $i;
    }
}

