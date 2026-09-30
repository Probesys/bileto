<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Repository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The autocomplete fetches choices separately; this hidden field maps its
 * selected ID back to an ongoing contract in the specified organization.
 * This server-side check rejects forged IDs for contracts outside the organization or validity period.
 *
 * @extends AbstractType<Entity\Contract>
 */
class ContractAutocompleteType extends AbstractType
{
    public function __construct(
        private readonly Repository\ContractRepository $contractRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?Entity\Contract $contract): string => $contract?->getId() ? (string) $contract->getId() : '',
            function (?string $id) use ($options): ?Entity\Contract {
                if ($id === null || $id === '') {
                    return null;
                }

                $contractId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $contract = $contractId !== false && $options['organization']
                    ? $this->contractRepository->findOngoingByIdAndOrganization(
                        $contractId,
                        $options['organization'],
                    )
                    : null;
                if (!$contract) {
                    $failure = new TransformationFailedException();
                    $failure->setInvalidMessage(new TranslatableMessage('contract.invalid', domain: 'errors'));
                    throw $failure;
                }

                return $contract;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'organization' => null,
        ]);

        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', [Entity\Organization::class, 'null']);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
