<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Repository;
use App\Service;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamType extends AbstractType
{
    public function __construct(
        private Repository\TeamRepository $teamRepository,
        private Service\Sorter\TeamSorter $teamSorter,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Entity\Team::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                $responsibleOnly = $options['responsible_only'];
                $organization = $options['organization'];

                $vary = [$organization, $responsibleOnly];

                return ChoiceList::lazy(
                    $this,
                    function () use ($organization, $responsibleOnly) {
                        if ($organization && $organization->getId() === null) {
                            $teams = [];
                        } elseif ($organization) {
                            $teams = $this->teamRepository->findByOrganization($organization);
                        } else {
                            $teams = $this->teamRepository->findAll();
                        }

                        if ($responsibleOnly) {
                            $teams = array_filter($teams, function ($team): bool {
                                return $team->isResponsible();
                            });
                        }

                        $this->teamSorter->sort($teams);

                        return $teams;
                    },
                    $vary,
                );
            },

            'choice_label' => 'name',
            'choice_value' => 'id',

            'choice_attr' => function (Entity\Team $team): array {
                $agentsIds = array_map(function (int $id): string {
                    return (string)$id;
                }, $team->getAgentsIds());
                return [
                    'data-agents-ids' => json_encode($agentsIds),
                ];
            },

            'responsible_only' => false,
            'organization' => null,
        ]);

        $resolver->setAllowedTypes('responsible_only', ['bool']);
        $resolver->setAllowedTypes('organization', [Entity\Organization::class, 'null']);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
