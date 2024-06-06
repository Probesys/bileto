<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Utils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
        ]);
        $builder->add('startAt', Type\DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'empty_data' => Utils\Time::now()->format('Y-m-d'),
        ]);
        $builder->add('endAt', Type\DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'empty_data' => Utils\Time::relative('last day of december')->format('Y-m-d'),
        ]);
        $builder->add('maxHours', Type\IntegerType::class, [
            'empty_data' => '0',
        ]);
        $builder->add('timeAccountingUnit', Type\IntegerType::class, [
            'required' => false,
            'empty_data' => '0',
        ]);
        $builder->add('notes', Type\TextareaType::class, [
            'required' => false,
            'empty_data' => '',
            'trim' => true,
        ]);
        $builder->add('associateTickets', Type\CheckboxType::class, [
            'required' => false,
            'mapped' => false,
            'data' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Contract::class,
            'csrf_token_id' => 'contract',
            'csrf_message' => 'csrf.invalid',
        ]);
    }
}
