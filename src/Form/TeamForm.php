<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
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
 * @extends AbstractType<Entity\Team>
 */
class TeamForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('teams.name'),
            'attr' => [
                'maxlength' => Entity\Team::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('isResponsible', Type\CheckboxType::class, [
            'required' => false,
            'label' => new TranslatableMessage('teams.is_responsible'),
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $team = $event->getData();

            if ($team->getId() === null) {
                $submitLabel = new TranslatableMessage('teams.new.submit');
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
            'data_class' => Entity\Team::class,
            'csrf_token_id' => 'team',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }
}
