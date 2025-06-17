<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Entity;
use App\Repository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;

class TicketEventChangesFormatterExtension
{
    public function __construct(
        private Repository\ContractRepository $contractRepository,
        private Repository\LabelRepository $labelRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Repository\TeamRepository $teamRepository,
        private Repository\UserRepository $userRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFilter('formatTicketChanges')]
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
        } elseif ($field === 'contracts') {
            return $this->formatContractsChanges($user, $fieldChanges);
        } elseif ($field === 'labels') {
            return $this->formatLabelsChanges($user, $fieldChanges);
        } elseif ($field === 'observers') {
            return $this->formatObserversChanges($user, $fieldChanges);
        } elseif ($field === 'organization') {
            return $this->formatOrganizationChanges($user, $fieldChanges);
        } elseif ($field === 'statusChangedAt') {
            // statusChangedAt is hidden to end user, so don't display this
            // change.
            return '';
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
    private function formatStatusChanges(?Entity\User $user, array $changes): string
    {
        if ($user) {
            $username = $this->escape($user->getDisplayName());
        } else {
            $username = $this->translator->trans('common.system_user');
        }
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
     * @param array<int[]> $changes
     */
    private function formatContractsChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        $removedContracts = $this->contractRepository->findBy([
          'id' => $changes[0],
        ]);
        $addedContracts = $this->contractRepository->findBy([
          'id' => $changes[1],
        ]);

        $removed = array_map(function ($contract): string {
            return $this->escape($contract->getName());
        }, $removedContracts);
        $removed = implode(', ', $removed);

        $added = array_map(function ($contract): string {
            return $this->escape($contract->getName());
        }, $addedContracts);
        $added = implode(', ', $added);

        if (empty($removed)) {
            return $this->translator->trans(
                'tickets.events.contracts.added',
                [
                    'username' => $username,
                    'added' => $added,
                ],
            );
        } elseif (empty($added)) {
            return $this->translator->trans(
                'tickets.events.contracts.removed',
                [
                    'username' => $username,
                    'removed' => $removed,
                ],
            );
        } else {
            return $this->translator->trans(
                'tickets.events.contracts.added_and_removed',
                [
                    'username' => $username,
                    'added' => $added,
                    'removed' => $removed,
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

        $removedLabels = $this->labelRepository->findBy([
          'id' => $changes[0],
        ]);
        $addedLabels = $this->labelRepository->findBy([
          'id' => $changes[1],
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

        if (empty($removed)) {
            return $this->translator->trans(
                'tickets.events.labels.added',
                [
                    'username' => $username,
                    'added' => $added,
                ],
            );
        } elseif (empty($added)) {
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
     * @param array<int[]> $changes
     */
    private function formatObserversChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());

        $removedObservers = $this->userRepository->findBy([
          'id' => $changes[0],
        ]);
        $addedObservers = $this->userRepository->findBy([
          'id' => $changes[1],
        ]);

        $removed = array_map(function ($user): string {
            return $this->escape($user->getDisplayName());
        }, $removedObservers);
        $removed = implode(', ', $removed);

        $added = array_map(function ($user): string {
            return $this->escape($user->getDisplayName());
        }, $addedObservers);
        $added = implode(', ', $added);

        if (empty($removed)) {
            return $this->translator->trans(
                'tickets.events.observers.added',
                [
                    'username' => $username,
                    'added' => $added,
                ],
            );
        } elseif (empty($added)) {
            return $this->translator->trans(
                'tickets.events.observers.removed',
                [
                    'username' => $username,
                    'removed' => $removed,
                ],
            );
        } else {
            return $this->translator->trans(
                'tickets.events.observers.added_and_removed',
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
    private function formatOrganizationChanges(Entity\User $user, array $changes): string
    {
        $username = $this->escape($user->getDisplayName());
        $oldOrganization = $this->organizationRepository->find($changes[0]);
        $oldOrganizationName = $this->escape($oldOrganization->getName());
        $newOrganization = $this->organizationRepository->find($changes[1]);
        $newOrganizationName = $this->escape($newOrganization->getName());

        return $this->translator->trans(
            'tickets.events.organization',
            [
                'username' => $username,
                'newValue' => $newOrganizationName,
                'oldValue' => $oldOrganizationName,
            ]
        );
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
