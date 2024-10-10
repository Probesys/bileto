<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class ActorsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $ticket = $event->getData();

            $organization = $ticket->getOrganization();

            $form->add('requester', AppType\ActorType::class, [
                'organization' => $organization,
                'label' => new TranslatableMessage('tickets.requester'),
            ]);

            $form->add('observers', AppType\ActorType::class, [
                'organization' => $organization,
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
                'label' => new TranslatableMessage('tickets.observers'),
            ]);

            $form->add('team', AppType\TeamType::class, [
                'organization' => $organization,
                'required' => false,
                'label' => new TranslatableMessage('tickets.team'),
                'placeholder' => new TranslatableMessage('tickets.team.none'),
                'attr' => [
                    'data-form-ticket-actors-target' => 'teams',
                    'data-action' => 'form-ticket-actors#refreshAssignees',
                ],
            ]);

            $form->add('assignee', AppType\ActorType::class, [
                'organization' => $organization,
                'roleType' => 'agent',
                'required' => false,
                'label' => new TranslatableMessage('tickets.assignee'),
                'placeholder' => new TranslatableMessage('tickets.unassigned'),
                'attr' => [
                    'data-form-ticket-actors-target' => 'assignees',
                ],
            ]);

            $form->add('submit', Type\SubmitType::class, [
                'label' => new TranslatableMessage('forms.save_changes'),
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $ticket = $event->getData();

            $team = $ticket->getTeam();
            $assignee = $ticket->getAssignee();

            if ($team === null || $assignee === null) {
                return;
            }

            if (!$team->hasAgent($assignee)) {
                $error = new FormError('The selected choice is invalid.');
                $form->get('assignee')->addError($error);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket actors',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-turbo-preserve-scroll' => true,
                'data-controller' => 'form-ticket-actors',
            ],
        ]);
    }
}
