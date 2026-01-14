<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Ticket;

use App\Entity;
use App\Security;
use App\Form\Type as AppType;
use App\SearchEngine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<SearchEngine\Ticket\QuickSearchFilter>
 */
class QuickSearchForm extends AbstractType
{
    public function __construct(
        private Security\Authorizer $authorizer,
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('text', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'required' => false,
            'label' => false,
            'attr' => [
                'aria-label' => $this->translator->trans('forms.search.quick_label'),
                'aria-placeholder' => $this->translator->trans('forms.search.quick_placeholder'),
                'autocomplete' => 'off',
            ],
        ]);

        $builder->add('groupStatuses', Type\ChoiceType::class, [
            'required' => false,
            'expanded' => true,
            'multiple' => true,
            'choices' => ['open', 'finished'],
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.status.{$choice}");
            },
            'label' => false,
            'choice_attr' => function (string $choice): array {
                return [
                    'data-checkboxes-target' => 'control',
                    'data-checkboxes-control' => "[data-status-group='{$choice}']#switch",
                    'data-action' => 'checkboxes#execute',
                ];
            },
            'attr' => [
                'class' => 'cols cols--always flow',
                'data-enclosure' => 'primary',
            ],
        ]);

        $builder->add('statuses', Type\ChoiceType::class, [
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => Entity\Ticket::STATUSES,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.status.{$choice}");
            },
            'choice_attr' => function (string $choice): array {
                $group = in_array($choice, Entity\Ticket::OPEN_STATUSES) ? 'open' : 'finished';
                return [
                    'data-status-group' => $group,
                    'data-checkboxes-control' => "input[value='{$group}']#uncheck",
                    'data-action' => 'checkboxes#execute',
                ];
            },
            'attr' => [
                'class' => 'flow flow--small',
            ],
            'label' => false,
        ]);

        $builder->add('labels', AppType\LabelType::class, [
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'label' => false,
            'block_prefix' => 'labels',
        ]);

        $builder->add('priorities', Type\ChoiceType::class, [
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => Entity\Ticket::WEIGHTS,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.priority.{$choice}");
            },
            'label' => false,
            'attr' => [
                'class' => 'flow flow--small',
            ],
        ]);

        $builder->add('urgencies', Type\ChoiceType::class, [
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => Entity\Ticket::WEIGHTS,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.urgency.{$choice}");
            },
            'label' => false,
            'attr' => [
                'class' => 'flow flow--small',
            ],
        ]);

        $builder->add('impacts', Type\ChoiceType::class, [
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => Entity\Ticket::WEIGHTS,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.impact.{$choice}");
            },
            'label' => false,
            'attr' => [
                'class' => 'flow flow--small',
            ],
        ]);

        $builder->add('type', Type\ChoiceType::class, [
            'empty_data' => '',
            'required' => false,
            'choices' => Entity\Ticket::TYPES,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("tickets.filters.type.{$choice}");
            },
            'placeholder' => new TranslatableMessage('tickets.filters.type.all'),
            'label' => new TranslatableMessage('tickets.filters.type.label'),
            'attr' => [
                'aria-label' => $this->translator->trans('tickets.filters.type.label'),
            ],
        ]);

        $builder->get('type')->addModelTransformer(
            new CallbackTransformer(
                function (?string $value): string {
                    return $value ?? '';
                },
                function (?string $value): string {
                    return $value ?? '';
                }
            )
        );

        $builder->add('from', Type\HiddenType::class, [
            'mapped' => false,
            'data' => $options['from'],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.search.submit'),
            'block_prefix' => 'submit_arrow',
            'attr' => [
                'class' => 'button',
            ],
            'row_attr' => [
                'class' => 'text--right',
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            if (!$this->authorizer->isAgent('any')) {
                return;
            }

            $form = $event->getForm();
            $organization = $options['organization'];

            $form->add('involves', AppType\ActorType::class, [
                'multiple' => true,
                'required' => false,
                'label' => new TranslatableMessage('tickets.involves'),
                'with_access_to' => $organization,
                'label_attr' => [
                    'class' => 'text--bold',
                ],
                'attr' => [
                    'data-placeholder' => $this->translator->trans('forms.multiselect.select_actor'),
                ],
                'block_prefix' => 'multiselect',
            ]);

            $form->add('teams', AppType\TeamType::class, [
                'multiple' => true,
                'required' => false,
                'label' => new TranslatableMessage('tickets.team'),
                'organization' => $organization,
                'label_attr' => [
                    'class' => 'text--bold',
                ],
                'attr' => [
                    'data-placeholder' => $this->translator->trans('forms.multiselect.select_team'),
                ],
                'block_prefix' => 'multiselect',
            ]);

            $form->add('assignees', AppType\ActorType::class, [
                'multiple' => true,
                'required' => false,
                'label' => new TranslatableMessage('tickets.assignee'),
                'with_access_to' => $organization,
                'role_type' => 'agent',
                'label_attr' => [
                    'class' => 'text--bold',
                ],
                'attr' => [
                    'data-placeholder' => $this->translator->trans('forms.multiselect.select_actor'),
                ],
                'block_prefix' => 'multiselect',
            ]);

            $form->add('unassignedOnly', Type\CheckboxType::class, [
                'required' => false,
                'label' => new TranslatableMessage('tickets.filters.assignee.no'),
                'attr' => [
                    'data-checkboxes-target' => 'control',
                    'data-checkboxes-control' => '#search_assignees-data#switchDisabled',
                    'data-action' => 'checkboxes#execute',
                ],
            ]);

            $form->add('requesters', AppType\ActorType::class, [
                'multiple' => true,
                'required' => false,
                'label' => new TranslatableMessage('tickets.requester'),
                'with_access_to' => $organization,
                'label_attr' => [
                    'class' => 'text--bold',
                ],
                'attr' => [
                    'data-placeholder' => $this->translator->trans('forms.multiselect.select_actor'),
                ],
                'block_prefix' => 'multiselect',
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchEngine\Ticket\QuickSearchFilter::class,
            'csrf_protection' => false,
            'attr' => [
                'class' => 'flow flow--large',
            ],
            'organization' => null,
            'from' => '/',
        ]);

        $resolver->setAllowedTypes('organization', [Entity\Organization::class, 'null']);
        $resolver->setAllowedTypes('from', 'string');
    }
}
