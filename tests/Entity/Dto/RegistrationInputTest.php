<?php

namespace App\Tests\Entity\Dto;

use App\Entity\Dto\Auth\RegistrationInput;
use App\Entity\Dto\Admin\AdminUserWriteInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class RegistrationInputTest extends TestCase
{
    private function violations(string $role): int
    {
        $input = new RegistrationInput();
        $input->prenom = 'Ada';
        $input->nom = 'Lovelace';
        $input->email = 'ada@example.com';
        $input->role = $role;
        $input->password = 'secret1';
        $input->confirmPassword = 'secret1';

        return count(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($input));
    }

    /** @dataProvider selfServiceRoles */
    public function testSignupRolesOfferedByTheForm(string $role): void
    {
        $this->assertSame(0, $this->violations($role));
    }

    /** @return iterable<string, array{string}> */
    public static function selfServiceRoles(): iterable
    {
        foreach (['employee', 'consultant', 'formateur', 'manager'] as $role) {
            yield $role => [$role];
        }
    }

    public function testVisitorCannotSignUpAsAdmin(): void
    {
        $this->assertSame(1, $this->violations('admin'));
    }

    public function testAdminFormAcceptsEveryRoleItOffers(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        foreach (['employee', 'consultant', 'formateur', 'manager', 'admin'] as $role) {
            $this->assertCount(0, $validator->validatePropertyValue(AdminUserWriteInput::class, 'role', $role), $role);
        }
    }
}
