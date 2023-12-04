<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Repository\UserRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @phpstan-import-type FilterValue from \App\SearchEngine\TicketFilter
 */
class TransFilterExtension extends AbstractExtension
{
    private UserRepository $userRepository;

    private TranslatorInterface $translator;

    public function __construct(
        UserRepository $userRepository,
        TranslatorInterface $translator,
    ) {
        $this->userRepository = $userRepository;
        $this->translator = $translator;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('transFilter', [$this, 'transFilter']),
        ];
    }

    /**
     * @param FilterValue[] $values
     *
     * @return string[]|FilterValue[]
     */
    public function transFilter(array $values, string $filter): array
    {
        if (
            $filter === 'status' ||
            $filter === 'priority' ||
            $filter === 'urgency' ||
            $filter === 'impact'
        ) {
            return array_map(function ($value) use ($filter): string {
                return $this->translator->trans("tickets.{$filter}.{$value}");
            }, $values);
        } elseif ($filter === 'type') {
            return array_map(function ($value): string {
                return $this->translator->trans("tickets.{$value}");
            }, $values);
        } elseif ($filter === 'assignee' || $filter === 'requester' || $filter === 'involves') {
            $users = [];

            foreach ($values as $value) {
                if ($value === null) {
                    $users[] = $this->translator->trans('tickets.unassigned');
                } elseif ($value === '@me') {
                    $users[] = ucfirst($this->translator->trans('users.yourself'));
                } else {
                    $user = $this->userRepository->find($value);
                    if ($user) {
                        $users[] = $user->getDisplayName();
                    }
                }

                if (count($users) >= 2) {
                    break;
                }
            }

            $diff = count($values) - count($users);
            if ($diff > 0) {
                $users[] = $this->translator->trans('users.n_others', ['number' => $diff]);
            }

            return $users;
        } else {
            return $values;
        }
    }
}
