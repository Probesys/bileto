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

class LabelType extends AbstractType
{
    public function __construct(
        private Repository\LabelRepository $labelRepository,
        private Service\Sorter\LabelSorter $labelSorter,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Entity\Label::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                return ChoiceList::lazy(
                    $this,

                    function () {
                        $labels = $this->labelRepository->findAll();
                        $this->labelSorter->sort($labels);
                        return $labels;
                    },
                );
            },

            'choice_label' => 'name',
            'choice_value' => 'id',

            'choice_attr' => function (Entity\Label $label): array {
                return [
                    'description' => $label->getDescription(),
                    'color' => $label->getColor(),
                ];
            },
        ]);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
