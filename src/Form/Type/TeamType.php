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
                $organization = $options['organization'];

                $vary = [$organization];

                return ChoiceList::lazy(
                    $this,
                    function () use ($organization) {
                        if ($organization) {
                            $teams = $this->teamRepository->findByOrganization($organization);
                        } else {
                            $teams = $this->teamRepository->findAll();
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
                    'agentsIds' => json_encode($agentsIds),
                ];
            },

            'organization' => null,
        ]);

        $resolver->setAllowedTypes('organization', [Entity\Organization::class, 'null']);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
