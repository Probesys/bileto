<?php

namespace App\Service;

class TicketServiceException extends \RuntimeException
{
    public const CODE_MISSING_ORGANIZATION = 0;
    public const CODE_CANNOT_CREATE_TICKET = 1;

    public static function missingOrganizationError(): self
    {
        return new self(
            code: self::CODE_MISSING_ORGANIZATION,
            message: 'The user is not attached to an organization.',
        );
    }

    public static function cannotCreateTicketError(): self
    {
        return new self(
            code: self::CODE_CANNOT_CREATE_TICKET,
            message: 'The user cannot create a ticket in the organization.',
        );
    }
}
