<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Organization;

use App\Form\Type as AppType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<array{
 *     organization: \App\Entity\Organization,
 * }>
 */
class SelectForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('organization', AppType\OrganizationType::class, [
            'permission' => $options['permission'],
            'required' => true,
            'label' => new TranslatableMessage('forms.organization.select.choose'),
        ]);

        if ($options['submit_label'] !== '') {
            $builder->add('submit', Type\SubmitType::class, [
                'label' => $options['submit_label'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'organization select',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
            'permission' => '',
            'submit_label' => '',
        ]);

        $resolver->setAllowedTypes('permission', 'string');
        $resolver->setAllowedTypes('submit_label', ['string', TranslatableMessage::class]);
    }
}
