<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Entity\Task>
 */
class TaskForm extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', Type\TextType::class, [
            'label' => new TranslatableMessage('forms.task.label.label'),
        ]);

        $builder->add('startAt', Type\DateTimeType::class, [
            'label' => new TranslatableMessage('forms.task.start_at.label'),
            'widget' => 'single_text',
            'with_seconds' => false,
            'attr' => [
                'data-form-task-target' => 'startAt',
                'data-action' => 'form-task#onStartAtChange',
            ],
        ]);

        $builder->add('endAt', Type\DateTimeType::class, [
            'label' => new TranslatableMessage('forms.task.end_at.label'),
            'widget' => 'single_text',
            'with_seconds' => false,
            'attr' => [
                'data-form-task-target' => 'endAt',
                'data-action' => 'form-task#onEndAtChange',
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $task = $event->getData();

            if ($task?->getId() !== null) {
                $form->add('submit', Type\SubmitType::class, [
                    'label' => new TranslatableMessage('forms.save_changes'),
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Task::class,
            'csrf_token_id' => 'task',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-controller' => 'form-task',
                'data-turbo-preserve-scroll' => true,
                'data-form-task-end-at-invalid-error-value' =>
                    $this->translator->trans('task.end_at.greater_than_start_at', domain: 'errors'),
            ],
        ]);
    }
}
