<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\TimeSpent>
 */
class TimeSpentForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('realTime', Type\IntegerType::class, [
            'label' => new TranslatableMessage('forms.time_spent.real_time.label'),
            'help' => new TranslatableMessage('forms.time_spent.real_time.help'),
            'empty_data' => '0',
            'required' => true,
            'attr' => [
                'class' => 'input--size3',
                'min' => 0,
                'autocomplete' => 'off',
            ],
        ]);

        $builder->add('mustNotBeAccounted', Type\CheckboxType::class, [
            'required' => false,
            'label' => new TranslatableMessage('forms.time_spent.must_not_be_accounted.label'),
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.save_changes'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\TimeSpent::class,
            'csrf_token_id' => 'time_spent',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-turbo-preserve-scroll' => true,
            ],
        ]);
    }
}
