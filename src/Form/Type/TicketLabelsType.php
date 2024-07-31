<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketLabelsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('labels', Type\EntityType::class, [
            'class' => Entity\Label::class,
            'query_builder' => function (EntityRepository $entityRepository): QueryBuilder {
                return $entityRepository->createQueryBuilder('l')
                                        ->orderBy('l.name', 'ASC');
            },
            'choice_label' => 'name',
            'choice_attr' => function (Entity\Label $label): array {
                return [
                    'description' => $label->getDescription(),
                    'color' => $label->getColor(),
                ];
            },
            'expanded' => true,
            'multiple' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket labels',
            'csrf_message' => 'csrf.invalid',
        ]);
    }
}
