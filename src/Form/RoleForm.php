<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Role>
 */
class RoleForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $type = $options['type'];

        $builder->add('type', Type\HiddenType::class, [
            'data' => $type,
        ]);

        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('roles.name'),
            'attr' => [
                'maxlength' => Entity\Role::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('description', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('roles.description'),
            'attr' => [
                'maxlength' => Entity\Role::DESCRIPTION_MAX_LENGTH,
            ],
        ]);

        $builder->add('permissions', Type\ChoiceType::class, [
            'choices' => Entity\Role::PERMISSIONS[$type],
            'expanded' => true,
            'multiple' => true,
            'label' => new TranslatableMessage('roles.permissions'),
        ]);

        if ($type === 'user') {
            $builder->add('isDefault', Type\CheckboxType::class, [
                'required' => false,
                'label' => new TranslatableMessage('roles.is_default'),
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $role = $event->getData();

            if ($role->getId() === null) {
                $submitLabel = new TranslatableMessage('roles.new.submit');
            } else {
                $submitLabel = new TranslatableMessage('forms.save_changes');
            }

            $form->add('submit', Type\SubmitType::class, [
                'label' => $submitLabel,
            ]);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($type): void {
            $data = $event->getData();

            // Make sure the type corresponds to the option value and not some
            // value that the user may have changed manually (i.e. we want to
            // force a valid value, and forbid to change the type of an
            // existing role);
            $data['type'] = $type;

            $permissions = $data['permissions'] ?? [];
            $permissions = Entity\Role::sanitizePermissions($type, $permissions);
            $data['permissions'] = $permissions;

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Role::class,
            'csrf_token_id' => 'role',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
            'type' => 'user',
        ]);

        $resolver->setAllowedValues('type', Entity\Role::TYPES);
    }
}
