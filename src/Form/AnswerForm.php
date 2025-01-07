<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Form\Type as AppType;
use App\Security;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
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

class AnswerForm extends AbstractType
{
    public function __construct(
        private Security\Authorizer $authorizer,
        private SymfonySecurity $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();

            $message = $event->getData();
            $ticket = $message->getTicket();
            $organization = $ticket->getOrganization();
            $requester = $ticket->getRequester();
            $assignee = $ticket->getAssignee();
            $ticketIsResolved = $ticket->getStatus() === 'resolved';
            $ticketHasSolution = $ticket->hasSolution();

            /** @var Entity\User */
            $currentUser = $this->security->getUser();

            $userIsAgent = $this->authorizer->isAgent($organization);
            $userIsRequester = $requester->getId() === $currentUser->getId();
            $userIsAssignee = $assignee?->getId() === $currentUser->getId();

            if ($userIsAgent) {
                $choices = ['normal'];

                if ($this->authorizer->isGranted('orga:create:tickets:messages:confidential', $organization)) {
                    $choices[] = 'confidential';
                }

                $canPostSolution = !$ticket->isFinished() && !$ticket->hasSolution() && $userIsAssignee;

                if ($canPostSolution) {
                    $choices[] = 'solution';
                }

                $form->add('type', Type\ChoiceType::class, [
                    'choices' => $choices,
                    'choice_label' => function (string $choice): TranslatableMessage {
                        return new TranslatableMessage("tickets.show.answer_type.{$choice}");
                    },
                    'mapped' => false,
                    'attr' => [
                        'class' => 'answer__select-answer-type widget--small',
                        'aria-label' => $this->translator->trans('tickets.show.answer_type'),
                    ],
                ]);

                if ($this->authorizer->isGranted('orga:create:tickets:time_spent', $organization)) {
                    $form->add('timeSpent', Type\IntegerType::class, [
                        'label' => new TranslatableMessage('tickets.show.minutes'),
                        'empty_data' => '0',
                        'mapped' => false,
                        'required' => false,
                        'attr' => [
                            'class' => 'input--size2 widget--small',
                            'min' => 0,
                            'autocomplete' => 'off',
                        ],
                    ]);
                }
            }

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

            $canPostSolutionApprovement = $ticketIsResolved && $ticketHasSolution;
            $canPostSolutionApprovement &= $userIsRequester || $userIsAssignee;

            if ($canPostSolutionApprovement) {
                if ($userIsAssignee) {
                    $choices = ['nothing', 'approve', 'refuse'];
                    $data = 'nothing';
                } else {
                    $choices = ['approve', 'refuse'];
                    $data = 'approve';
                }

                $form->add('solutionAction', Type\ChoiceType::class, [
                    'label' => new TranslatableMessage('tickets.show.answer_solution'),
                    'data' => $data,
                    'choices' => $choices,
                    'choice_label' => function (string $choice): TranslatableMessage {
                        return new TranslatableMessage("tickets.show.answer_solution.{$choice}");
                    },
                    'expanded' => true,
                    'mapped' => false,
                    'row_attr' => [
                        'class' => 'panel answer__solution-panel flow',
                    ],
                    'attr' => [
                        'class' => 'cols cols--center flow',
                    ],
                ]);
            }

            $form->add('submit', Type\SubmitType::class, [
                'label' => new TranslatableMessage('tickets.show.answer'),
                'block_prefix' => 'submit_arrow',
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $message = $event->getData();

            $message->setVia('webapp');

            $isConfidential = $form->has('type') && $form->get('type')->getData() === 'confidential';

            if ($isConfidential) {
                $message->setIsConfidential(true);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Message::class,
            'csrf_token_id' => 'answer',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'timeline__editor flow',
                'data-controller' => 'form-message-documents',
                'data-turbo-preserve-scroll' => true,
            ],
        ]);
    }
}
