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
 * @extends AbstractType<Entity\Organization>
 */
class OrganizationType extends AbstractType
{
    public function __construct(
        private Repository\OrganizationRepository $organizationRepository,
        private Security\Authorizer $authorizer,
        private Service\Sorter\OrganizationSorter $organizationSorter,
        private SymfonySecurity $security,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /** @var ?Entity\User */
        $currentUser = $this->security->getUser();

        $resolver->setDefaults([
            'class' => Entity\Organization::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                $contextUser = $options['context_user'];
                $permission = $options['permission'];

                $vary = [$contextUser, $permission];

                return ChoiceList::lazy(
                    $this,
                    function () use ($contextUser, $permission): array {
                        return $this->loadOrganizations($contextUser, $permission);
                    },
                    $vary,
                );
            },

            'choice_label' => 'name',
            'choice_value' => 'id',

            'permission' => '',
            'context_user' => $currentUser,
        ]);

        $resolver->setAllowedTypes('permission', 'string');
        $resolver->setAllowedTypes('context_user', Entity\User::class);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }

    /**
     * @return Entity\Organization[]
     */
    private function loadOrganizations(Entity\User $contextUser, string $permission): array
    {
        if ($permission && $contextUser->getId() === null) {
            return [];
        }

        $organizations = $this->organizationRepository->findAll();

        if ($permission) {
            $organizations = array_filter(
                $organizations,
                function ($organization) use ($contextUser, $permission): bool {
                    return $this->authorizer->isGrantedToUser(
                        $contextUser,
                        $permission,
                        $organization
                    );
                }
            );
        }

        $this->organizationSorter->sort($organizations);

        return $organizations;
    }
}
