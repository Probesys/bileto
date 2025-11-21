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
 * @extends AbstractType<Entity\MessageTemplate>
 */
class MessageTemplateForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('message_templates.name'),
            'attr' => [
                'maxlength' => Entity\MessageTemplate::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('type', Type\ChoiceType::class, [
            'choices' => Entity\MessageTemplate::TYPES,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("message_templates.type.{$choice}");
            },
            'label' => new TranslatableMessage('message_templates.type'),
        ]);

        $builder->add('content', Type\TextareaType::class, [
            'empty_data' => '',
            'trim' => true,
            'sanitize_html' => true,
            'sanitizer' => 'app.message_sanitizer',
            'block_prefix' => 'editor',
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $messageTemplate = $event->getData();

            if ($messageTemplate->getId() === null) {
                $submitLabel = new TranslatableMessage('message_templates.new.submit');
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
            'data_class' => Entity\MessageTemplate::class,
            'csrf_token_id' => 'message_template',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'flow flow--large wrapper wrapper--center',
            ],
        ]);
    }
}
