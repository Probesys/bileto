<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Ticket>
 */
class OngoingContractForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            $ticket = $event->getData();

            $form->add('ongoingContract', AppType\ContractAutocompleteType::class, [
                'required' => false,
                'organization' => $ticket->getOrganization(),
                'data' => $ticket->getOngoingContract() ?? $options['preferred_contract'],
                'label' => new TranslatableMessage('tickets.contracts.ongoing'),
                'attr' => [
                    'data-contract-select-target' => 'value',
                ],
            ]);

            $form->add('includeUnaccountedTime', Type\CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'data' => true,
                'label' => new TranslatableMessage('tickets.contracts.edit.associate_unaccounted_times'),
            ]);

            $form->add('submit', Type\SubmitType::class, [
                'label' => new TranslatableMessage('forms.save_changes'),
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket contract',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-turbo-preserve-scroll' => true,
            ],
            'preferred_contract' => null,
        ]);

        $resolver->setAllowedTypes('preferred_contract', [Entity\Contract::class, 'null']);
    }
}
