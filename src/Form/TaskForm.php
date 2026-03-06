<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Task>
 */
class TaskForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', Type\TextType::class, [
            'label' => new TranslatableMessage('forms.task.label.label'),
        ]);

        $builder->add('startAt', Type\DateTimeType::class, [
            'label' => new TranslatableMessage('forms.task.start_at.label'),
            'widget' => 'single_text',
            'with_seconds' => false,
        ]);

        $builder->add('endAt', Type\DateTimeType::class, [
            'label' => new TranslatableMessage('forms.task.end_at.label'),
            'widget' => 'single_text',
            'with_seconds' => false,
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.submit'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Task::class,
            'csrf_token_id' => 'task',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-turbo-preserve-scroll' => true,
            ],
        ]);
    }
}
