<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganizationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('organization', Type\OrganizationType::class, [
            'permission' => 'orga:update:tickets:organization',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket organization',
            'csrf_message' => 'csrf.invalid',
        ]);
    }
}
