<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Utils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Contract>
 */
class ContractForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('contracts.form.name'),
            'attr' => [
                'maxlength' => Entity\Contract::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('startAt', Type\DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'empty_data' => Utils\Time::now()->format('Y-m-d'),
            'label' => new TranslatableMessage('contracts.form.start_at'),
            'attr' => [
                'data-form-contract-target' => 'startAt',
                'data-action' => 'form-contract#updateEndAt',
            ],
        ]);

        $builder->add('endAt', Type\DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'empty_data' => Utils\Time::relative('last day of december')->format('Y-m-d'),
            'label' => new TranslatableMessage('contracts.form.end_at'),
            'attr' => [
                'data-form-contract-target' => 'endAt',
            ],
        ]);

        $builder->add('maxHours', Type\IntegerType::class, [
            'empty_data' => '0',
            'label' => new TranslatableMessage('contracts.form.max_hours'),
            'attr' => [
                'min' => 0,
                'step' => 1,
                'class' => 'input--size3',
            ],
        ]);

        $builder->add('timeAccountingUnit', Type\IntegerType::class, [
            'required' => false,
            'empty_data' => '0',
            'label' => new TranslatableMessage('contracts.form.time_accounting_unit'),
            'help' => new TranslatableMessage('contracts.form.time_accounting_unit.caption'),
            'attr' => [
                'min' => 0,
                'step' => 1,
                'class' => 'input--size3',
            ],
        ]);

        $builder->add('notes', Type\TextareaType::class, [
            'required' => false,
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('contracts.form.notes'),
        ]);

        $allowAssociate = $options['allow_associate'];

        if ($allowAssociate) {
            $builder->add('associateTickets', Type\CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'data' => true,
                'label' => new TranslatableMessage('contracts.form.associate_tickets'),
            ]);

            $builder->add('associateUnaccountedTimes', Type\CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'data' => true,
                'label' => new TranslatableMessage('contracts.form.associate_unaccounted_times'),
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $contract = $event->getData();

            if ($contract->getId() === null) {
                $submitLabel = new TranslatableMessage('contracts.form.new');
            } else {
                $submitLabel = new TranslatableMessage('forms.save_changes');
            }

            $form->add('submit', Type\SubmitType::class, [
                'label' => $submitLabel,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Contract::class,
            'csrf_token_id' => 'contract',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-controller' => 'form-contract',
            ],
            'allow_associate' => false,
        ]);

        $resolver->setAllowedTypes('allow_associate', ['bool']);
    }
}
