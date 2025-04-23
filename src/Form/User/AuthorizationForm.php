<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\User;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Entity\Authorization>
 */
class AuthorizationForm extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('type', Type\ChoiceType::class, [
            'choices' => ['user', 'agent', 'admin'],
            'expanded' => true,
            'multiple' => false,
            'mapped' => false,
            'label' => false,
            'data' => 'user',
            'attr' => [
                'class' => 'cols cols--always flow',
                'data-enclosure' => 'primary',
            ],
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("roles.type.{$choice}");
            },
            'choice_attr' => function (string $choice): array {
                return [
                    'data-action' => 'form-new-authorization#refresh',
                ];
            },
        ]);

        $builder->add('role', AppType\RoleType::class, [
            'label' => new TranslatableMessage('authorizations.new.role'),

            'attr' => [
                'aria-describedby' => 'role-caption',
                'data-action' => 'form-new-authorization#refreshRoleCaption',
                'data-form-new-authorization-target' => 'roleSelect',
            ],

            'choice_attr' => function (Entity\Role $role): array {
                $type = $role->getType();

                $description = $role->getDescription();

                if ($type === 'super') {
                    $description = $this->translator->trans('roles.super_admin.description');
                }

                return [
                    'data-type' => $type,
                    'data-desc' => $description,
                    'data-form-new-authorization-target' => 'roleOption',
                ];
            },
        ]);

        $builder->add('organization', AppType\OrganizationType::class, [
            'required' => false,
            'placeholder' => new TranslatableMessage('authorizations.new.global'),
            'label' => new TranslatableMessage('authorizations.new.organization_scope'),
            'help' => new TranslatableMessage('authorizations.new.organization_caption'),
            'attr' => [
                'data-form-new-authorization-target' => 'organizations',
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('authorizations.new.submit'),
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $authorization = $event->getData();

            $role = $authorization->getRole();
            if ($role && $role->isAdmin()) {
                $authorization->setOrganization(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Authorization::class,
            'csrf_token_id' => 'authorization',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-controller' => 'form-new-authorization',
            ],
        ]);
    }
}
