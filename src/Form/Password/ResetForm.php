<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Password;

use App\Entity;
use App\Repository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<array{
 *     user: Entity\User,
 * }>
 */
class ResetForm extends AbstractType
{
    public function __construct(
        private Repository\UserRepository $userRepository,
        #[Autowire(env: 'bool:LDAP_ENABLED')]
        private bool $ldapEnabled,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', Type\EmailType::class, [
            'label' => new TranslatableMessage('passwords.reset.form.email'),
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('passwords.reset.form.submit'),
        ]);

        $transformer = new CallbackTransformer(
            function (?Entity\User $user): string {
                if (!$user) {
                    return '';
                }

                return $user->getEmail();
            },
            function (string $email): Entity\User {
                $user = $this->userRepository->findOneBy([
                    'email' => $email,
                ]);

                if (!$user || !$user->canLogin()) {
                    $failure = new TransformationFailedException('User with given email does not exist');
                    $failureMessage = new TranslatableMessage('reset_password.user.unknown', [], 'errors');
                    $failure->setInvalidMessage($failureMessage);
                    throw $failure;
                }

                if ($this->ldapEnabled && $user->getAuthType() === 'ldap') {
                    $failure = new TransformationFailedException('User with given email does not exist');
                    $failureMessage = new TranslatableMessage('reset_password.user.managed_by_ldap', [], 'errors');
                    $failure->setInvalidMessage($failureMessage);
                    throw $failure;
                }

                return $user;
            }
        );

        $builder->get('user')->addModelTransformer($transformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'reset password',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }
}
