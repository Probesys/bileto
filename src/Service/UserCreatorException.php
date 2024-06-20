<?php

namespace App\Service;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class UserCreatorException extends \RuntimeException
{
    public function __construct(
        private ConstraintViolationListInterface $errors,
        string $message = '',
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }

    public function getErrors(): ConstraintViolationListInterface
    {
        return $this->errors;
    }
}
