<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\Entity;
use App\Service;
use App\Utils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * @extends AbstractType<Entity\Organization>
 */
class ArchiveOrganizationForm extends AbstractType
{
    public function __construct(
        private Service\ActorsLister $actorsLister,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('deletedAt', Type\DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'required' => false,
            'label' => new TranslatableMessage('organizations.settings.archive.delete_on'),
            'help' => new TranslatableMessage('organizations.settings.archive.delete_on.caption'),
            'constraints' => [
                new Assert\Callback(
                    function (
                        ?\DateTimeImmutable $value,
                        ExecutionContextInterface $context,
                    ): void {
                        if ($value === null) {
                            return;
                        }

                        if ($value <= Utils\Time::now()) {
                            $context
                                ->buildViolation(new TranslatableMessage(
                                    'organization.deleted_at.in_future',
                                    [],
                                    'errors'
                                ))
                                ->addViolation();
                        }
                    }
                ),
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $form = $event->getForm();
            /** @var Entity\Organization */
            $organization = $event->getData();

            $users = $this->actorsLister->findByOrganization($organization, roleType: 'user');

            $form->add('usersToArchive', EntityType::class, [
                'class' => Entity\User::class,
                'choices' => $users,
                'choice_label' => function (Entity\User $user): string {
                    return $user->getDisplayName() ?? $user->getEmail() ?? '';
                },
                'choice_value' => 'id',
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'required' => false,
                'label' => new TranslatableMessage('organizations.settings.archive.users.label'),
                'help' => new TranslatableMessage('organizations.settings.archive.users.caption'),
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entity\Organization::class,
            'csrf_token_id' => 'archive organization',
            'csrf_message' => 'csrf.invalid',
            'attr' => [
                'class' => 'form--standard',
            ],
        ]);
    }
}
