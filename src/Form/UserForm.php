<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Form\Type as AppType;
use App\Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\User>
 */
class UserForm extends AbstractType
{
    public function __construct(
        private Service\Locales $locales,
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

            if ($user->getUid() === null) {
                $user->setLocale($this->locales->getDefaultLocale());
            }

            $form->add('email', Type\EmailType::class, [
                'empty_data' => '',
                'trim' => true,
                'disabled' => $managedByLdap,
                'label' => new TranslatableMessage('users.email'),
            ]);

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

            $form->add('locale', Type\ChoiceType::class, [
                'choices' => array_flip(Service\Locales::SUPPORTED_LOCALES),
                'label' => new TranslatableMessage('users.language'),
            ]);

            if ($this->ldapEnabled) {
                $form->add('ldapIdentifier', Type\TextType::class, [
                    'empty_data' => '',
                    'trim' => true,
                    'required' => false,
                    'label' => new TranslatableMessage('users.ldap_identifier'),
                ]);
            }

            $form->add('preventLogin', Type\CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'data' => !$user->canLogin(),
                'label' => new TranslatableMessage('users.form.prevent_login'),
                'attr' => [
                    'data-checkboxes-target' => 'control',
                    'data-checkboxes-control' => '#user_plainPassword#switchDisabled',
                    'data-action' => 'checkboxes#execute',
                ],
            ]);

            if (!$managedByLdap) {
                $help = null;

                if ($user->getUid() !== null) {
                    $help = new TranslatableMessage('users.form.password.empty_keep_current');
                }

                $form->add('plainPassword', Type\PasswordType::class, [
                    'empty_data' => '',
                    'hash_property_path' => 'password',
                    'mapped' => false,
                    'required' => false,
                    'label' => new TranslatableMessage('users.password'),
                    'help' => $help,
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ]);
            }

            $form->add('organization', AppType\OrganizationType::class, [
                'permission' => 'orga:create:tickets',
                'context_user' => $user,
                'required' => false,
                'placeholder' => new TranslatableMessage('users.organization.auto'),
                'label' => new TranslatableMessage('users.organization'),
                'help' => new TranslatableMessage('users.form.organization_caption'),
            ]);

            if ($user->getId() === null) {
                $submitLabel = new TranslatableMessage('users.new.submit');
            } else {
                $submitLabel = new TranslatableMessage('forms.save_changes');
            }

            $form->add('submit', Type\SubmitType::class, [
                'label' => $submitLabel,
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $user = $event->getData();

            $preventLogin = $form->get('preventLogin')->getData();
            if ($preventLogin) {
                $user->disableLogin();
            } else {
                $user->allowLogin();
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
            'csrf_token_id' => 'user',
            'csrf_message' => 'csrf.invalid',
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
