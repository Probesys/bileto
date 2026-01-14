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
 * @extends AbstractType<Entity\Label>
 */
class LabelForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('labels.name'),
            'attr' => [
                'maxlength' => Entity\Label::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('description', Type\TextType::class, [
            'required' => false,
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('labels.description'),
            'attr' => [
                'maxlength' => Entity\Label::DESCRIPTION_MAX_LENGTH,
            ],
        ]);

        $builder->add('color', Type\ChoiceType::class, [
            'choices' => Entity\Label::COLORS,
            'expanded' => true,
            'multiple' => false,
            'label' => new TranslatableMessage('labels.color'),
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $label = $event->getData();

            if ($label->getId() === null) {
                $submitLabel = new TranslatableMessage('labels.new.submit');
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
            'data_class' => Entity\Label::class,
            'csrf_token_id' => 'label',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }
}
