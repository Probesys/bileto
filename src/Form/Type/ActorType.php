<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Service;
use App\Utils;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Entity\User|Entity\User[]|null>
 */
class ActorType extends AbstractType implements DataMapperInterface
{
    public function __construct(
        private Service\ActorCreator $actorCreator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('select', ActorSelectType::class, [
            'with_access_to' => $options['with_access_to'],
            'role_type' => $options['role_type'],
            'required' => $options['required'],
            'multiple' => $options['multiple'],
            'placeholder' => $options['placeholder'],
            'attr' => $options['attr'],
            'label' => false,
            'error_bubbling' => true,
        ]);

        if ($options['allow_email']) {
            if ($options['multiple']) {
                $builder->add('emails', Type\CollectionType::class, [
                    'entry_type' => Type\EmailType::class,
                    'entry_options' => [
                        'trim' => true,
                        'error_bubbling' => true,
                    ],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'required' => false,
                    'label' => false,
                ]);
            } else {
                $builder->add('email', Type\EmailType::class, [
                    'required' => false,
                    'trim' => true,
                    'empty_data' => '',
                    'label' => false,
                    'error_bubbling' => true,
                ]);
            }
        }

        $builder->setDataMapper($this);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['allow_email'] = $options['allow_email'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'with_access_to' => null,
            'role_type' => 'any',
            'allow_email' => false,
            'multiple' => false,
            'placeholder' => null,
            'error_bubbling' => false,
            'data_class' => function (Options $options): ?string {
                if ($options['multiple']) {
                    return null;
                } else {
                    return Entity\User::class;
                }
            },
            'empty_data' => function (Options $options): ?array {
                if ($options['multiple']) {
                    return [];
                } else {
                    return null;
                }
            },
        ]);

        $resolver->setAllowedTypes('with_access_to', [
            Entity\Organization::class,
            Entity\Ticket::class,
            'null',
        ]);
        $resolver->setAllowedValues('role_type', Service\ActorsLister::VALID_ROLE_TYPES);
        $resolver->setAllowedTypes('allow_email', 'bool');
        $resolver->setAllowedTypes('multiple', 'bool');
    }

    /**
     * @param \Traversable<FormInterface<mixed>> $forms
     */
    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        $forms = iterator_to_array($forms);

        if ($viewData instanceof Collection) {
            $viewData = $viewData->toArray();
        }

        $forms['select']->setData($viewData);
    }

    /**
     * @param \Traversable<string, FormInterface<mixed>> $forms
     */
    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);

        if (isset($forms['emails'])) {
            $users = $forms['select']->getData() ?? [];

            foreach ($forms['emails'] as $emailForm) {
                $email = $emailForm->getData();

                if (!$email) {
                    continue;
                }

                $user = $this->findOrBuildByEmail($email, $emailForm, prefixEmailOnError: true);

                if ($user && !in_array($user, $users)) {
                    $users[] = $user;
                }
            }

            $viewData = $users;
        } elseif (isset($forms['email']) && $forms['email']->getData()) {
            $email = $forms['email']->getData();

            $user = $this->findOrBuildByEmail($email, $forms['email']);

            if ($user) {
                $viewData = $user;
            }
        } else {
            $viewData = $forms['select']->getData();
        }
    }

    /**
     * @param FormInterface<?mixed> $errorTarget
     */
    private function findOrBuildByEmail(
        string $email,
        FormInterface $errorTarget,
        bool $prefixEmailOnError = false,
    ): ?Entity\User {
        try {
            // $flush = false so the actor is not created if there is an error
            // in the form. It will be saved once the parent element (e.g.
            // ticket) is saved.
            return $this->actorCreator->findOrCreateByEmail($email, flush: false);
        } catch (Service\UserCreatorException $e) {
            $messages = Utils\ConstraintErrorsFormatter::format($e->getErrors());

            foreach ($messages as $message) {
                if ($prefixEmailOnError) {
                    $message = "{$email}: {$message}";
                }

                $error = new FormError($message);
                $errorTarget->addError($error);
            }

            return null;
        }
    }

    public function getBlockPrefix(): string
    {
        return 'actor';
    }
}
