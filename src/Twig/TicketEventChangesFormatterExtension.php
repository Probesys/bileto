<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Entity;
use App\Repository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TicketEventChangesFormatterExtension extends AbstractExtension
{
    public function __construct(
        private Repository\ContractRepository $contractRepository,
        private Repository\LabelRepository $labelRepository,
        private Repository\TeamRepository $teamRepository,
        private Repository\UserRepository $userRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('formatTicketChanges', [$this, 'formatTicketChanges']),
        ];
    }

    public function formatTicketChanges(Entity\EntityEvent $event, string $field): string
    {
        $user = $event->getCreatedBy();
        $changes = $event->getChanges();

        if (!isset($changes[$field])) {
            throw new \LogicException("Cannot format {$field} changes of the EntityEvent");
        }

        $fieldChanges = $changes[$field];

        if ($field === 'title') {
            return $this->formatTitleChanges($user, $fieldChanges);
        } elseif ($field === 'status') {
            return $this->formatStatusChanges($user, $fieldChanges);
        } elseif ($field === 'type') {
            return $this->formatTypeChanges($user, $fieldChanges);
        } elseif ($field === 'impact' || $field === 'priority' || $field === 'urgency') {
            return $this->formatPriorityChanges($user, $field, $fieldChanges);
        } elseif ($field === 'assignee') {
            return $this->formatAssigneeChanges($user, $fieldChanges);
        } elseif ($field === 'requester') {
            return $this->formatRequesterChanges($user, $fieldChanges);
        } elseif ($field === 'team') {
            return $this->formatTeamChanges($user, $fieldChanges);
        } elseif ($field === 'solution') {
            return $this->formatSolutionChanges($user, $fieldChanges);
        } elseif ($field === 'ongoingContract') {
            return $this->formatOngoingContractChanges($user, $fieldChanges);
        } elseif ($field === 'labels') {
            return $this->formatLabelsChanges($user, $fieldChanges);
        } else {
            return $this->formatChanges($user, $field, $fieldChanges);
        }
    }

    /**
     * @param string[] $changes
     */
    private function formatTitleChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());
        $oldValue = $this->escape($changes[0] ?? '');
        $newValue = $this->escape($changes[1] ?? '');

        return $this->translator->trans(
            'tickets.events.title',
            [
                'username' => $username,
                'oldValue' => $oldValue,
                'newValue' => $newValue,
            ]
        );
    }

    /**
     * @param string[] $changes
     */
    private function formatStatusChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());
        $oldValue = $this->translator->trans('tickets.status.' . $changes[0]);
        $newValue = $this->translator->trans('tickets.status.' . $changes[1]);
        return $this->translator->trans(
            'tickets.events.status',
            [
                'username' => $username,
                'oldValue' => $oldValue,
                'newValue' => $newValue,
            ]
        );
    }

    /**
     * @param string[] $changes
     */
    private function formatTypeChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        if ($changes[1] === 'request') {
            return $this->translator->trans(
                'tickets.events.type.to_request',
                ['username' => $username],
            );
        } elseif ($changes[1] === 'incident') {
            return $this->translator->trans(
                'tickets.events.type.to_incident',
                ['username' => $username],
            );
        } else {
            throw new \LogicException("Ticket event with type change contains an invalid value ({$changes[1]})");
        }
    }

    /**
     * @param string[] $changes
     */
    private function formatPriorityChanges(Entity\User $user, string $field, array $changes): string
    {
        $parameters = [
            'username' => $this->escape($user->getDisplayName()),
            'oldValue' => $this->translator->trans("tickets.{$field}.{$changes[0]}"),
            'newValue' => $this->translator->trans("tickets.{$field}.{$changes[1]}"),
        ];

        // We can't build the key dynamically, or the extractor would delete the translations.
        if ($field === 'impact') {
            return $this->translator->trans('tickets.events.impact', $parameters);
        } elseif ($field === 'priority') {
            return $this->translator->trans('tickets.events.priority', $parameters);
        } elseif ($field === 'urgency') {
            return $this->translator->trans('tickets.events.urgency', $parameters);
        } else {
            throw new \LogicException("formatPriorityChanges cannot be called with {$field} field");
        }
    }

    /**
     * @param array<?int> $changes
     */
    private function formatAssigneeChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        if ($changes[0] === null) {
            $newAssignee = $this->userRepository->find($changes[1]);
            $newAssigneeUsername = $this->escape($newAssignee->getDisplayName());
            return $this->translator->trans(
                'tickets.events.assignee.assigned',
                [
                    'username' => $username,
                    'newValue' => $newAssigneeUsername,
                ]
            );
        } elseif ($changes[1] === null) {
            $oldAssignee = $this->userRepository->find($changes[0]);
            $oldAssigneeUsername = $this->escape($oldAssignee->getDisplayName());
            return $this->translator->trans(
                'tickets.events.assignee.unassigned',
                [
                    'username' => $username,
                    'oldValue' => $oldAssigneeUsername,
                ]
            );
        } else {
            $oldAssignee = $this->userRepository->find($changes[0]);
            $oldAssigneeUsername = $this->escape($oldAssignee->getDisplayName());
            $newAssignee = $this->userRepository->find($changes[1]);
            $newAssigneeUsername = $this->escape($newAssignee->getDisplayName());
            return $this->translator->trans(
                'tickets.events.assignee.changed',
                [
                    'username' => $username,
                    'newValue' => $newAssigneeUsername,
                    'oldValue' => $oldAssigneeUsername,
                ]
            );
        }
    }

    /**
     * @param int[] $changes
     */
    private function formatRequesterChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());
        $oldRequester = $this->userRepository->find($changes[0]);
        $oldRequesterUsername = $this->escape($oldRequester->getDisplayName());
        $newRequester = $this->userRepository->find($changes[1]);
        $newRequesterUsername = $this->escape($newRequester->getDisplayName());

        return $this->translator->trans(
            'tickets.events.requester',
            [
                'username' => $username,
                'newValue' => $newRequesterUsername,
                'oldValue' => $oldRequesterUsername,
            ]
        );
    }

    /**
     * @param array<?int> $changes
     */
    private function formatTeamChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        if ($changes[0] === null) {
            $newTeam = $this->teamRepository->find($changes[1]);
            if ($newTeam) {
                $newTeamName = $this->escape($newTeam->getName());
            } else {
                $newTeamName = $this->translator->trans('tickets.events.team.deleted');
            }

            return $this->translator->trans(
                'tickets.events.team.set',
                [
                    'username' => $username,
                    'newValue' => $newTeamName,
                ]
            );
        } elseif ($changes[1] === null) {
            $oldTeam = $this->teamRepository->find($changes[0]);
            if ($oldTeam) {
                $oldTeamName = $this->escape($oldTeam->getName());
            } else {
                $oldTeamName = $this->translator->trans('tickets.events.team.deleted');
            }

            return $this->translator->trans(
                'tickets.events.team.unset',
                [
                    'username' => $username,
                    'oldValue' => $oldTeamName,
                ]
            );
        } else {
            $oldTeam = $this->teamRepository->find($changes[0]);
            if ($oldTeam) {
                $oldTeamName = $this->escape($oldTeam->getName());
            } else {
                $oldTeamName = $this->translator->trans('tickets.events.team.deleted');
            }

            $newTeam = $this->teamRepository->find($changes[1]);
            if ($newTeam) {
                $newTeamName = $this->escape($newTeam->getName());
            } else {
                $newTeamName = $this->translator->trans('tickets.events.team.deleted');
            }

            return $this->translator->trans(
                'tickets.events.team.changed',
                [
                    'username' => $username,
                    'newValue' => $newTeamName,
                    'oldValue' => $oldTeamName,
                ]
            );
        }
    }

    /**
     * @param array<?int> $changes
     */
    private function formatSolutionChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        if ($changes[0] === null) {
            return $this->translator->trans(
                'tickets.events.solution.new',
                ['username' => $username],
            );
        } elseif ($changes[1] === null) {
            return $this->translator->trans(
                'tickets.events.solution.removed',
                ['username' => $username],
            );
        } else {
            return $this->translator->trans(
                'tickets.events.solution.changed',
                ['username' => $username],
            );
        }
    }

    /**
     * @param array<?int> $changes
     */
    private function formatOngoingContractChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        if ($changes[0] === null) {
            $newContract = $this->contractRepository->find($changes[1]);
            $newContractName = $this->escape($newContract->getName());
            return $this->translator->trans(
                'tickets.events.ongoing_contract.new',
                [
                    'username' => $username,
                    'newValue' => $newContractName,
                ],
            );
        } elseif ($changes[1] === null) {
            $oldContract = $this->contractRepository->find($changes[0]);
            $oldContractName = $this->escape($oldContract->getName());
            return $this->translator->trans(
                'tickets.events.ongoing_contract.removed',
                [
                    'username' => $username,
                    'oldValue' => $oldContractName,
                ],
            );
        } else {
            $oldContract = $this->contractRepository->find($changes[0]);
            $oldContractName = $this->escape($oldContract->getName());
            $newContract = $this->contractRepository->find($changes[1]);
            $newContractName = $this->escape($newContract->getName());
            return $this->translator->trans(
                'tickets.events.ongoing_contract.changed',
                [
                    'username' => $username,
                    'oldValue' => $oldContractName,
                    'newValue' => $newContractName,
                ],
            );
        }
    }

    /**
     * @param array<int[]> $changes
     */
    private function formatLabelsChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        $removedLabelsIds = array_diff($changes[0], $changes[1]);
        $addedLabelsIds = array_diff($changes[1], $changes[0]);

        $removedLabels = $this->labelRepository->findBy([
          'id' => $removedLabelsIds,
        ]);
        $addedLabels = $this->labelRepository->findBy([
          'id' => $addedLabelsIds,
        ]);

        $removed = array_map(function ($label): string {
            $name = $this->escape($label->getName());
            $color = $this->escape($label->getColor());
            return "<span class=\"badge badge--{$color}\">{$name}</span>";
        }, $removedLabels);
        $removed = implode(', ', $removed);

        $added = array_map(function ($label): string {
            $name = $this->escape($label->getName());
            $color = $this->escape($label->getColor());
            return "<span class=\"badge badge--{$color}\">{$name}</span>";
        }, $addedLabels);
        $added = implode(', ', $added);

        if (empty($removedLabelsIds)) {
            return $this->translator->trans(
                'tickets.events.labels.added',
                [
                    'username' => $username,
                    'added' => $added,
                ],
            );
        } elseif (empty($addedLabelsIds)) {
            return $this->translator->trans(
                'tickets.events.labels.removed',
                [
                    'username' => $username,
                    'removed' => $removed,
                ],
            );
        } else {
            return $this->translator->trans(
                'tickets.events.labels.added_and_removed',
                [
                    'username' => $username,
                    'added' => $added,
                    'removed' => $removed,
                ],
            );
        }
    }

    /**
     * @param mixed[] $changes
     */
    private function formatChanges(Entity\User $user, string $field, array $changes): string
    {
        $parameters = [
            'username' => $this->escape($user->getDisplayName()),
            'field' => $field,
            'oldValue' => $this->escape($changes[0] ?? ''),
            'newValue' => $this->escape($changes[1] ?? ''),
        ];

        if ($changes[0] === null) {
            return $this->translator->trans('tickets.events.default.new', $parameters);
        } elseif ($changes[1] === null) {
            return $this->translator->trans('tickets.events.default.removed', $parameters);
        } else {
            return $this->translator->trans('tickets.events.default.changed', $parameters);
        }
    }

    private function escape(string $string): string
    {
        return htmlspecialchars($string, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
