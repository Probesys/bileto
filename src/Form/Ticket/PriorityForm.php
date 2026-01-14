<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Ticket>
 */
class PriorityForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('urgency', Type\ChoiceType::class, [
            'choices' => Entity\Ticket::WEIGHTS,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.urgency.{$choice}");
            },
            'label' => new TranslatableMessage('tickets.urgency'),
            'attr' => [
                'data-form-priority-target' => 'urgency',
                'data-action' => 'form-priority#updatePriority',
            ],
        ]);

        $builder->add('impact', Type\ChoiceType::class, [
            'choices' => Entity\Ticket::WEIGHTS,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.impact.{$choice}");
            },
            'label' => new TranslatableMessage('tickets.impact'),
            'attr' => [
                'data-form-priority-target' => 'impact',
                'data-action' => 'form-priority#updatePriority',
            ],
        ]);

        $builder->add('priority', Type\ChoiceType::class, [
            'choices' => Entity\Ticket::WEIGHTS,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.priority.{$choice}");
            },
            'label' => new TranslatableMessage('tickets.priority'),
            'attr' => [
                'data-form-priority-target' => 'priority',
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.save_changes'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket priority',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-controller' => 'form-priority',
                'data-turbo-preserve-scroll' => true,
            ],
        ]);
    }
}
