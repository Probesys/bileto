<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganizationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
        ]);
        $builder->add('domains', Type\CollectionType::class, [
            'entry_type' => Type\TextType::class,
            'entry_options' => [
                'trim' => true,
            ],
            'allow_add' => true,
            'allow_delete' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Organization::class,
            'csrf_token_id' => 'organization',
            'csrf_message' => 'csrf.invalid',
        ]);
    }
}
