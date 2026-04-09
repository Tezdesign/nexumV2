<?php

namespace App\Controller\Trait;

use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ValidationFlashTrait
{
    protected function flashValidationErrors(ConstraintViolationListInterface $violations): bool
    {
        if ($violations->count() === 0) {
            return false;
        }

        foreach ($violations as $violation) {
            $path = $violation->getPropertyPath();
            $msg = $violation->getMessage();
            $this->addFlash('error', ($path !== '' ? $path . ': ' : '') . $msg);
        }

        return true;
    }
}
