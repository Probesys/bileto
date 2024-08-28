<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Repository;
use App\Security;
use App\Service;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganizationType extends AbstractType
{
    public function __construct(
        private Repository\OrganizationRepository $organizationRepository,
        private Security\Authorizer $authorizer,
        private Service\Sorter\OrganizationSorter $organizationSorter,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Entity\Organization::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                $permission = $options['permission'];

                $vary = [$permission];

                return ChoiceList::lazy(
                    $this,
                    function () use ($permission) {
                        $organizations = $this->organizationRepository->findAll();

                        if ($permission) {
                            $organizations = array_filter(
                                $organizations,
                                function ($organization) use ($permission): bool {
                                    return $this->authorizer->isGranted($permission, $organization);
                                }
                            );
                        }

                        $this->organizationSorter->sort($organizations);

                        return $organizations;
                    },
                    $vary,
                );
            },

            'choice_label' => 'name',
            'choice_value' => 'id',

            'permission' => '',
        ]);

        $resolver->setAllowedTypes('permission', 'string');
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
