<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Form\Type as AppType;
use App\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Entity\Ticket>
 */
class TicketForm extends AbstractType
{
    public function __construct(
        private Security\Authorizer $authorizer,
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $ticket = $event->getData();
            $organization = $ticket->getOrganization();

            if ($this->authorizer->isGranted('orga:update:tickets:actors', $organization)) {
                $form->add('requester', AppType\ActorType::class, [
                    'with_access_to' => $organization,
                    'label' => new TranslatableMessage('tickets.requester'),
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
                    'with_access_to' => $organization,
                    'role_type' => 'agent',
                    'required' => false,
                    'label' => new TranslatableMessage('tickets.assignee'),
                    'placeholder' => new TranslatableMessage('tickets.unassigned'),
                    'attr' => [
                        'data-form-ticket-actors-target' => 'assignees',
                    ],
                ]);

                $form->add('observers', AppType\ActorType::class, [
                    'with_access_to' => $organization,
                    'multiple' => true,
                    'by_reference' => false,
                    'required' => false,
                    'label' => new TranslatableMessage('tickets.observers'),
                    'attr' => [
                        'data-placeholder' => $this->translator->trans('forms.multiselect.select_actor'),
                    ],
                    'block_prefix' => 'multiselect',
                ]);
            }

            if ($this->authorizer->isGranted('orga:update:tickets:priority', $organization)) {
                $form->add('urgency', Type\ChoiceType::class, [
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

                $form->add('impact', Type\ChoiceType::class, [
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

                $form->add('priority', Type\ChoiceType::class, [
                    'choices' => Entity\Ticket::WEIGHTS,
                    'choice_label' => function (string $choice): TranslatableMessage {
                        return new TranslatableMessage("tickets.priority.{$choice}");
                    },
                    'label' => new TranslatableMessage('tickets.priority'),
                    'attr' => [
                        'data-form-priority-target' => 'priority',
                    ],
                ]);
            }

            if ($this->authorizer->isGranted('orga:update:tickets:labels', $organization)) {
                $form->add('labels', AppType\LabelType::class, [
                    'expanded' => true,
                    'multiple' => true,
                    'by_reference' => false,
                    'required' => false,
                    'label' => false,
                    'block_prefix' => 'labels',
                ]);
            }

            if ($this->authorizer->isGranted('orga:update:tickets:type', $organization)) {
                $form->add('type', Type\ChoiceType::class, [
                    'choices' => Entity\Ticket::TYPES,
                    'choice_label' => function (string $choice): TranslatableMessage {
                        return new TranslatableMessage("tickets.type.{$choice}");
                    },
                    'expanded' => true,
                    'label' => false,
                    'attr' => [
                        'class' => 'cols cols--always flow',
                        'data-enclosure' => 'primary',
                    ],
                ]);
            }

            $form->add('title', Type\TextType::class, [
                'empty_data' => '',
                'trim' => true,
                'label' => new TranslatableMessage('tickets.title'),
                'attr' => [
                    'maxlength' => Entity\Ticket::TITLE_MAX_LENGTH,
                ],
            ]);

            $form->add('content', Type\TextareaType::class, [
                'empty_data' => '',
                'trim' => true,
                'sanitize_html' => true,
                'sanitizer' => 'app.message_sanitizer',
                'block_prefix' => 'editor',
                'constraints' => [
                    new Assert\NotBlank(
                        message: new TranslatableMessage('message.content.required', [], 'errors'),
                    ),
                ],
            ]);

            $form->add('submit', Type\SubmitType::class, [
                'label' => new TranslatableMessage('tickets.new.submit'),
                'block_prefix' => 'submit_arrow',
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $ticket = $event->getData();

            $isResolved = $form->has('isResolved') && $form->get('isResolved')->getData();

            if ($isResolved) {
                $ticket->setStatus('resolved');
            }

            $team = $ticket->getTeam();
            $assignee = $ticket->getAssignee();

            $hasTeam = $team !== null;
            $hasAssignee = $assignee !== null;

            if ($hasTeam && $hasAssignee && !$team->hasAgent($assignee)) {
                $error = new FormError('The selected choice is invalid.');
                $form->get('assignee')->addError($error);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Ticket::class,
            'csrf_token_id' => 'ticket',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'flow flow--larger',
                'data-controller' => 'form-priority form-ticket-actors',
            ],
        ]);
    }
}
