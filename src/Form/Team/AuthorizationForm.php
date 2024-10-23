<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Team;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class AuthorizationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('role', AppType\RoleType::class, [
            'types' => ['agent'],

            'label' => new TranslatableMessage('authorizations.new.role'),

            'attr' => [
                'aria-describedby' => 'role-caption',
                'data-action' => 'form-new-authorization#refreshRoleCaption',
                'data-form-new-authorization-target' => 'roleSelect',
            ],

            'choice_attr' => function (Entity\Role $role): array {
                $type = $role->getType();

                $description = $role->getDescription();

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
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('authorizations.new.submit'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\TeamAuthorization::class,
            'csrf_token_id' => 'authorization',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                'data-controller' => 'form-new-authorization',
            ],
        ]);
    }
}
