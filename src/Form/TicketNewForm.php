<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as SymfonyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketNewForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $organization = $options['organization'];

        $builder->add('requester', Type\ActorType::class, [
            'organization' => $organization,
        ]);

        $builder->add('team', Type\TeamType::class, [
            'organization' => $organization,
        ]);

        $builder->add('assignee', Type\ActorType::class, [
            'organization' => $organization,
            'roleType' => 'agent',
        ]);

        $builder->add('labels', Type\LabelType::class, [
            'expanded' => true,
            'multiple' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket',
            'csrf_message' => 'csrf.invalid',
            'organization' => null,
        ]);

        $resolver->setRequired(['organization']);
        $resolver->setAllowedTypes('organization', [Entity\Organization::class]);
    }
}
