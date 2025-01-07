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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleType extends AbstractType
{
    public function __construct(
        private Repository\RoleRepository $roleRepository,
        private Service\Sorter\RoleSorter $roleSorter,
        private Security\Authorizer $authorizer,
        private TranslatorInterface $translator,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Entity\Role::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                $allowedTypes = ['user', 'agent', 'admin', 'super'];
                $types = $options['types'];

                if (empty($types)) {
                    // No types is all types.
                    $types = $allowedTypes;
                } else {
                    // Make sure that the types only contains allowed ones.
                    $types = array_intersect($allowedTypes, $types);
                }

                if (!$this->authorizer->isGranted('admin:*')) {
                    // Remove "super" type if permission is not granted.
                    $types = array_diff($types, ['super']);
                }

                $vary = [$types];

                return ChoiceList::lazy(
                    $this,
                    function () use ($types) {
                        $roles = $this->roleRepository->findBy([
                            'type' => $types,
                        ]);

                        $this->roleSorter->sort($roles);

                        return $roles;
                    },
                    $vary,
                );
            },

            'choice_value' => 'id',

            'choice_label' => function (Entity\Role $role): string {
                if ($role->getType() === 'super') {
                    return $this->translator->trans('roles.super_admin');
                } else {
                    return $role->getName();
                }
            },

            'types' => [],
        ]);

        $resolver->setAllowedTypes('types', 'array');
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
