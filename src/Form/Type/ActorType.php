<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Service;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActorType extends AbstractType
{
    public function __construct(
        private Security $security,
        private Service\ActorsLister $actorsLister,
        private TranslatorInterface $translator,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /** @var Entity\User */
        $currentUser = $this->security->getUser();

        $resolver->setDefaults([
            'class' => Entity\User::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                $organization = $options['organization'];
                $roleType = $options['roleType'];

                $vary = [$organization, $roleType];

                return ChoiceList::lazy(
                    $this,
                    function () use ($organization, $roleType): array {
                        if ($organization) {
                            return $this->actorsLister->findByOrganization($organization, $roleType);
                        } else {
                            return $this->actorsLister->findAll($roleType);
                        }
                    },
                    $vary,
                );
            },

            'choice_label' => function (Entity\User $user) use ($currentUser): string {
                $label = $user->getDisplayName();

                if ($user->getId() === $currentUser->getId()) {
                    $yourself = $this->translator->trans('users.yourself');
                    $label .= " ({$yourself})";
                }

                return $label;
            },

            'choice_value' => 'id',

            'organization' => null,
            'roleType' => 'any',
        ]);

        $resolver->setAllowedTypes('organization', [Entity\Organization::class, 'null']);
        $resolver->setAllowedValues('roleType', Service\ActorsLister::VALID_ROLE_TYPES);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
