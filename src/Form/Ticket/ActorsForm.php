<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActorsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $ticket = $event->getData();

            $organization = $ticket->getOrganization();

            $form->add('requester', Type\ActorType::class, [
                'organization' => $organization,
                'required' => true,
            ]);

            $form->add('team', Type\TeamType::class, [
                'organization' => $organization,
            ]);

            $form->add('assignee', Type\ActorType::class, [
                'organization' => $organization,
                'roleType' => 'agent',
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
        ]);
    }
}
