<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\User;

use App\Entity;
use App\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class PreferencesForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('locale', Type\ChoiceType::class, [
            'choices' => array_flip(Service\Locales::SUPPORTED_LOCALES),
            'label' => new TranslatableMessage('users.language'),
            'label_attr' => [
                'data-icon' => 'language',
            ],
        ]);

        $builder->add('colorScheme', Type\ChoiceType::class, [
            'choices' => ['auto', 'light', 'dark'],
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("users.color_scheme.{$choice}");
            },
            'label' => new TranslatableMessage('users.color_scheme'),
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('forms.save_changes'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\User::class,
            'csrf_token_id' => 'preferences',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
                // The form can change attributes of the <html> tag, so we need
                // to force the refresh of the page.
                'data-turbo' => 'false',
            ],
        ]);
    }
}
