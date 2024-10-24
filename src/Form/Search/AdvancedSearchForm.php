<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Search;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdvancedSearchForm extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('mode', Type\HiddenType::class, [
            'data' => 'advanced',
        ]);

        $builder->add('q', Type\TextareaType::class, [
            'empty_data' => '',
            'trim' => true,
            'required' => false,
            'label' => false,
            'attr' => [
                'aria-label' => $this->translator->trans('tickets.search.label'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'attr' => [
                'class' => 'flow flow--small',
            ],
        ]);
    }
}
