<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Security;
use App\Service;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActorType extends AbstractType
{
    public function __construct(
        private SymfonySecurity $security,
        private Security\Authorizer $authorizer,
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
                $withAccessTo = $options['with_access_to'];
                $roleType = $options['role_type'];

                $vary = [$withAccessTo, $roleType];

                return ChoiceList::lazy(
                    $this,
                    function () use ($withAccessTo, $roleType): array {
                        if ($withAccessTo instanceof Entity\Organization) {
                            $users = $this->actorsLister->findByOrganization($withAccessTo, $roleType);
                            $scope = $withAccessTo;
                        } elseif ($withAccessTo instanceof Entity\Ticket) {
                            $users = $this->actorsLister->findByTicket($withAccessTo, $roleType);
                            $scope = $withAccessTo->getOrganization();
                        } else {
                            $users = $this->actorsLister->findAll($roleType);
                            $scope = 'any';
                        }

                        if ($roleType !== 'any') {
                            return $users;
                        }

                        $usersGroupLabel = $this->translator->trans('forms.actors.group.users');
                        $agentsGroupLabel = $this->translator->trans('forms.actors.group.agents');

                        $groupedUsers = [
                            $usersGroupLabel => [],
                            $agentsGroupLabel => [],
                        ];

                        foreach ($users as $user) {
                            if ($this->authorizer->isUserAgent($user, $scope)) {
                                $groupedUsers[$agentsGroupLabel][] = $user;
                            } else {
                                $groupedUsers[$usersGroupLabel][] = $user;
                            }
                        }

                        return $groupedUsers;
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

            'with_access_to' => null,
            'role_type' => 'any',
        ]);

        $resolver->setAllowedTypes('with_access_to', [
            Entity\Organization::class,
            Entity\Ticket::class,
            'null',
        ]);
        $resolver->setAllowedValues('role_type', Service\ActorsLister::VALID_ROLE_TYPES);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
