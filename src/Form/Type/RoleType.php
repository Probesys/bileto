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
                $vary = [];

                return ChoiceList::lazy(
                    $this,
                    function () {
                        $roles = $this->roleRepository->findBy([
                            'type' => ['user', 'agent', 'admin'],
                        ]);

                        if ($this->authorizer->isGranted('admin:*')) {
                            $roles[] = $this->roleRepository->findOrCreateSuperRole();
                        }

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
        ]);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
