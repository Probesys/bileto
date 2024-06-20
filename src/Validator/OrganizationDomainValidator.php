<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Validator;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use App\Utils;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class OrganizationDomainValidator extends ConstraintValidator
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OrganizationDomain) {
            throw new UnexpectedTypeException($constraint, OrganizationDomain::class);
        }

        $currentOrganization = $this->context->getObject();

        if (!$currentOrganization instanceof Organization) {
            throw new UnexpectedTypeException($currentOrganization, Organization::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $isValid = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
        $isValid = $isValid && str_contains($value, '.');
        $isValid = $isValid || $value === '*';

        $domain = Utils\Url::domainToUtf8($value);

        if ($isValid === false) {
            $this->context->buildViolation($constraint->messageInvalid)
                ->setParameter('{{ domain }}', $domain)
                ->addViolation();
            return;
        }

        $existingOrganization = $this->organizationRepository->findOneByDomain($value);

        if ($existingOrganization !== null && $currentOrganization->getUid() !== $existingOrganization->getUid()) {
            $this->context->buildViolation($constraint->messageDuplicated)
                ->setParameter('{{ domain }}', $domain)
                ->setParameter('{{ organization }}', $existingOrganization->getName())
                ->addViolation();
        }
    }
}
