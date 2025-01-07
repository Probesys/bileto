<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class OrganizationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('organizations.name'),
            'attr' => [
                'maxlength' => Entity\Organization::TITLE_MAX_LENGTH,
            ],
        ]);

        $builder->add('domains', Type\CollectionType::class, [
            'entry_type' => Type\TextType::class,
            'entry_options' => [
                'trim' => true,
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'required' => false,
            'label' => new TranslatableMessage('organizations.domains'),
            'help' => new TranslatableMessage('organizations.domains.caption'),
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $organization = $event->getData();

            $form->add('responsibleTeam', AppType\TeamType::class, [
                'organization' => $organization,
                'responsible_only' => true,
                'required' => false,
                'label' => new TranslatableMessage('organizations.responsible_team'),
                'help' => new TranslatableMessage('organizations.responsible_team.caption'),
                'placeholder' => new TranslatableMessage('organizations.responsible_team.auto'),
            ]);

            if ($organization->getId() === null) {
                $submitLabel = new TranslatableMessage('organizations.new.submit');
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
            'data_class' => Entity\Organization::class,
            'csrf_token_id' => 'organization',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }
}
