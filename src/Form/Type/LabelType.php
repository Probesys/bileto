<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
        ]);

        $builder->add('description', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
        ]);

        $builder->add('color', Type\HiddenType::class, [
            'data' => '#e0e1e6',
            'trim' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Label::class,
            'csrf_token_id' => 'label',
            'csrf_message' => 'csrf.invalid',
        ]);
    }
}
