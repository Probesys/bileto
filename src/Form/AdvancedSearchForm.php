<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Form;

use App\SearchEngine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<array{
 *     'q': SearchEngine\Query,
 * }>
 */
class AdvancedSearchForm extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('mode', Type\HiddenType::class, [
            'data' => 'advanced',
            'empty_data' => 'advanced',
        ]);

        $builder->add('q', Type\TextareaType::class, [
            'trim' => true,
            'empty_data' => '',
            'required' => false,
            'label' => false,
            'attr' => [
                'aria-label' => $this->translator->trans('forms.search.advanced_label'),
            ],
        ]);

        $builder->get('q')->addModelTransformer(new CallbackTransformer(
            function (?SearchEngine\Query $query): string {
                return $query ? $query->getString() : '';
            },
            function (string $queryString): SearchEngine\Query {
                try {
                    return SearchEngine\Query::fromString($queryString);
                } catch (SearchEngine\Query\SyntaxError $e) {
                    $failure = new TransformationFailedException($e->getMessage());
                    $message = new TranslatableMessage('forms.search.syntax_invalid', domain: 'errors');
                    $failure->setInvalidMessage($message);
                    throw $failure;
                }
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
            'attr' => [
                'class' => 'flow flow--small',
            ],
        ]);
    }
}
