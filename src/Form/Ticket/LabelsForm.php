<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Ticket>
 */
class LabelsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('labels', AppType\LabelType::class, [
            'expanded' => true,
            'multiple' => true,
            'by_reference' => false,
            'required' => false,
            'label' => false,
            'block_prefix' => 'labels',
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.save_changes'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket labels',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-turbo-preserve-scroll' => true,
            ],
        ]);
    }
}
