<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\User;

use App\Entity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\User>
 */
class ProfileForm extends AbstractType
{
    public function __construct(
        #[Autowire(env: 'bool:LDAP_ENABLED')]
        private bool $ldapEnabled,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            $user = $event->getData();
            $managedByLdap = $this->isManagedByLdap($user);

            $form->add('name', Type\TextType::class, [
                'empty_data' => '',
                'required' => false,
                'trim' => true,
                'disabled' => $managedByLdap,
                'label' => new TranslatableMessage('users.name'),
                'attr' => [
                    'maxlength' => Entity\User::NAME_MAX_LENGTH,
                    'autocomplete' => 'name',
                ],
            ]);

            $form->add('email', Type\EmailType::class, [
                'empty_data' => '',
                'trim' => true,
                'disabled' => $managedByLdap,
                'label' => new TranslatableMessage('users.email'),
                'attr' => [
                    'autocomplete' => 'email',
                ],
            ]);

            if (!$managedByLdap) {
                $form->add('currentPassword', Type\PasswordType::class, [
                    'empty_data' => '',
                    'mapped' => false,
                    'required' => false,
                    'label' => new TranslatableMessage('profile.current_password'),
                    'attr' => [
                        'autocomplete' => 'current-password',
                    ],
                    'constraints' => [
                        new SecurityAssert\UserPassword(
                            message: new TranslatableMessage('user.password.dont_match', [], 'errors'),
                            groups: ['change_password'],
                        ),
                    ],
                ]);

                $form->add('plainPassword', Type\PasswordType::class, [
                    'empty_data' => '',
                    'hash_property_path' => 'password',
                    'mapped' => false,
                    'required' => false,
                    'label' => new TranslatableMessage('profile.new_password'),
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ]);

                $form->add('submit', Type\SubmitType::class, [
                    'label' => new TranslatableMessage('forms.save_changes'),
                ]);
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $user = $form->getData();
        $view->vars['managedByLdap'] = $this->isManagedByLdap($user);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\User::class,
            'csrf_token_id' => 'profile',
            'csrf_message' => 'csrf.invalid',
            'validation_groups' => function (FormInterface $form): array {
                $groups = ['Default'];

                if (!$form->has('plainPassword')) {
                    return $groups;
                }

                $plainPassword = $form->get('plainPassword')->getData();

                if ($plainPassword) {
                    $groups[] = 'change_password';
                }

                return $groups;
            },
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }

    private function isManagedByLdap(Entity\User $user): bool
    {
        return $this->ldapEnabled && $user->getAuthType() === 'ldap';
    }
}
