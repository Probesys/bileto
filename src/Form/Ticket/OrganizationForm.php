<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class OrganizationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('organization', AppType\OrganizationType::class, [
            'permission' => 'orga:update:tickets:organization',
            'label' => new TranslatableMessage('tickets.organization'),
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('tickets.organization.edit.transfer'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket organization',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-turbo-preserve-scroll' => true,
            ],
        ]);
    }
}
