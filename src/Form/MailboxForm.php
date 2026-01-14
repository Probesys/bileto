<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\Mailbox>
 */
class MailboxForm extends AbstractType
{
    public function __construct(
        private Security\Encryptor $encryptor,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('mailboxes.name'),
            'attr' => [
                'maxlength' => Entity\Mailbox::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('host', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('mailboxes.host'),
            'attr' => [
                'maxlength' => Entity\Mailbox::HOST_MAX_LENGTH,
            ],
        ]);

        $builder->add('port', Type\NumberType::class, [
            'label' => new TranslatableMessage('mailboxes.port'),
            'html5' => true,
            'attr' => [
                'min' => Entity\Mailbox::PORT_RANGE[0],
                'max' => Entity\Mailbox::PORT_RANGE[1],
            ],
        ]);

        $builder->add('encryption', Type\ChoiceType::class, [
            'choices' => Entity\Mailbox::ENCRYPTION_VALUES,
            'choice_label' => function (string $choice): TranslatableMessage {
                return new TranslatableMessage("mailboxes.encryption.{$choice}");
            },
            'label' => new TranslatableMessage('mailboxes.encryption'),
        ]);

        $builder->add('username', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('mailboxes.username'),
            'attr' => [
                'maxlength' => Entity\Mailbox::USERNAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('folder', Type\TextType::class, [
            'empty_data' => '',
            'trim' => true,
            'label' => new TranslatableMessage('mailboxes.folder'),
            'attr' => [
                'maxlength' => Entity\Mailbox::FOLDER_MAX_LENGTH,
            ],
        ]);

        $builder->add('postAction', Type\ChoiceType::class, [
            'choices' => Entity\Mailbox::POST_ACTION_VALUES,
            'choice_label' => function (string $choice): TranslatableMessage {
                $choice = str_replace(' ', '_', $choice);
                return new TranslatableMessage("mailboxes.post_action.{$choice}");
            },
            'label' => new TranslatableMessage('mailboxes.post_action'),
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $mailbox = $event->getData();

            $help = '';
            if ($mailbox->getId() !== null) {
                $help = new TranslatableMessage('mailboxes.edit.leave_password_empty');
            }

            $form->add('plainPassword', Type\PasswordType::class, [
                'empty_data' => '',
                'required' => false,
                'mapped' => false,
                'label' => new TranslatableMessage('mailboxes.password'),
                'help' => $help,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ]);

            if ($mailbox->getId() === null) {
                $submitLabel = new TranslatableMessage('mailboxes.new.submit');
            } else {
                $submitLabel = new TranslatableMessage('forms.save_changes');
            }

            $form->add('submit', Type\SubmitType::class, [
                'label' => $submitLabel,
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $mailbox = $event->getData();

            $plainPassword = $form->get('plainPassword')->getData();

            if ($plainPassword) {
                $password = $this->encryptor->encrypt($plainPassword);
                $mailbox->setPassword($password);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Mailbox::class,
            'csrf_token_id' => 'mailbox',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }
}
