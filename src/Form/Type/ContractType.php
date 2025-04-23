<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Repository;
use App\Security;
use App\Service;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Entity\Contract>
 */
class ContractType extends AbstractType
{
    public function __construct(
        private Repository\ContractRepository $contractRepository,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Entity\Contract::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                $ongoing = $options['ongoing'];

                $vary = [$ongoing];

                return ChoiceList::lazy(
                    $this,
                    function () use ($ongoing): array {
                        if ($ongoing) {
                            $contracts = $this->contractRepository->findOngoingByOrganization($ongoing);
                        } else {
                            $contracts = $this->contractRepository->findAll();
                        }

                        return $contracts;
                    },
                    $vary,
                );
            },

            'choice_label' => 'name',
            'choice_value' => 'id',

            'ongoing' => null,
        ]);

        $resolver->setAllowedTypes('ongoing', [Entity\Organization::class, 'null']);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
