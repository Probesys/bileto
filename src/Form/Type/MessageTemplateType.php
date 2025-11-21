<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form\Type;

use App\Entity;
use App\Repository;
use App\Service;
use Symfony\Bridge\Doctrine\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends AbstractType<Entity\MessageTemplate>
 */
class MessageTemplateType extends AbstractType
{
    public function __construct(
        private Repository\MessageTemplateRepository $messageTemplateRepository,
        private Service\Sorter\MessageTemplateSorter $messageTemplateSorter,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Entity\MessageTemplate::class,

            'choice_loader' => function (Options $options): ChoiceLoaderInterface {
                return ChoiceList::lazy(
                    $this,
                    function (): array {
                        $messageTemplates = $this->messageTemplateRepository->findAll();
                        $this->messageTemplateSorter->sort($messageTemplates);
                        return $messageTemplates;
                    },
                );
            },

            'group_by' => function ($choice, $key, $value): TranslatableMessage {
                return $choice->getTypeLabel();
            },

            'choice_label' => 'name',
            'choice_value' => 'id',

            'choice_attr' => function (Entity\MessageTemplate $messageTemplate): array {
                return [
                    'data-type' => $messageTemplate->getType(),
                    'data-content' => $messageTemplate->getContent(),
                ];
            },
        ]);
    }

    public function getParent(): string
    {
        return Type\EntityType::class;
    }
}
