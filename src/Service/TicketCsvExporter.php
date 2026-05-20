<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketCsvExporter
{
    public function __construct(
        private Security\Authorizer $authorizer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param iterable<Entity\Ticket> $tickets
     */
    public function stream(iterable $tickets, string $locale): \Closure
    {
        return function () use ($tickets, $locale): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            $this->writeHeader($handle, $locale);

            foreach ($tickets as $ticket) {
                $this->writeRow($handle, $locale, $ticket);
                fflush($handle);
            }

            fclose($handle);
        };
    }

    /**
     * @param resource $handle
     */
    private function writeHeader($handle, string $locale): void
    {
        fputcsv($handle, [
            $this->translator->trans('tickets.export.column.id', locale: $locale),
            $this->translator->trans('tickets.export.column.uid', locale: $locale),
            $this->translator->trans('tickets.export.column.created_at', locale: $locale),
            $this->translator->trans('tickets.export.column.created_by', locale: $locale),
            $this->translator->trans('tickets.export.column.updated_at', locale: $locale),
            $this->translator->trans('tickets.export.column.updated_by', locale: $locale),
            $this->translator->trans('tickets.export.column.type', locale: $locale),
            $this->translator->trans('tickets.export.column.status', locale: $locale),
            $this->translator->trans('tickets.export.column.title', locale: $locale),
            $this->translator->trans('tickets.export.column.urgency', locale: $locale),
            $this->translator->trans('tickets.export.column.impact', locale: $locale),
            $this->translator->trans('tickets.export.column.priority', locale: $locale),
            $this->translator->trans('tickets.export.column.requester', locale: $locale),
            $this->translator->trans('tickets.export.column.assignee', locale: $locale),
            $this->translator->trans('tickets.export.column.team', locale: $locale),
            $this->translator->trans('tickets.export.column.observers', locale: $locale),
            $this->translator->trans('tickets.export.column.organization', locale: $locale),
            $this->translator->trans('tickets.export.column.solution', locale: $locale),
            $this->translator->trans('tickets.export.column.contracts', locale: $locale),
            $this->translator->trans('tickets.export.column.time_spent.real', locale: $locale),
            $this->translator->trans('tickets.export.column.time_spent.accounted', locale: $locale),
            $this->translator->trans('tickets.export.column.time_spent.unaccounted', locale: $locale),
            $this->translator->trans('tickets.export.column.labels', locale: $locale),
        ], escape: '');
    }

    /**
     * @param resource $handle
     */
    private function writeRow($handle, string $locale, Entity\Ticket $ticket): void
    {
        $organization = $ticket->getOrganization();

        $isAgent = $this->authorizer->isAgent($organization);
        $canSeeContracts = $this->authorizer->isGranted('orga:see:tickets:contracts', $organization);
        $canSeeRealTimeSpent = $this->authorizer->isGranted('orga:see:tickets:time_spent:real', $organization);
        $canSeeAccountedTimeSpent = $this->authorizer->isGranted(
            'orga:see:tickets:time_spent:accounted',
            $organization,
        );

        $observers = array_map(
            function (Entity\User $observer) use ($isAgent): string {
                return $this->exportUser($observer, exportEmail: $isAgent);
            },
            $ticket->getObservers()->toArray(),
        );
        $contracts = array_map(
            function (Entity\Contract $contract): string {
                return $this->sanitizeField($contract->getName() ?? '');
            },
            $ticket->getContracts()->toArray(),
        );
        $labels = array_map(
            function (Entity\Label $label): string {
                return $this->sanitizeField($label->getName() ?? '');
            },
            $ticket->getLabels()->toArray(),
        );

        if ($ticket->hasSolution()) {
            $solutionLabel = $this->translator->trans('tickets.export.solution.yes', locale: $locale);
        } else {
            $solutionLabel = $this->translator->trans('tickets.export.solution.no', locale: $locale);
        }

        fputcsv($handle, [
            $ticket->getId(),
            $ticket->getUid(),
            $ticket->getCreatedAt()?->format('Y-m-d H:i') ?? '',
            $this->exportUser($ticket->getCreatedBy(), exportEmail: $isAgent),
            $ticket->getUpdatedAt()?->format('Y-m-d H:i') ?? '',
            $this->exportUser($ticket->getUpdatedBy(), exportEmail: $isAgent),
            $this->translator->trans("tickets.type.{$ticket->getType()}", locale: $locale),
            $this->translator->trans("tickets.status.{$ticket->getStatus()}", locale: $locale),
            $this->sanitizeField($ticket->getTitle()),
            $this->translator->trans("tickets.urgency.{$ticket->getUrgency()}", locale: $locale),
            $this->translator->trans("tickets.impact.{$ticket->getImpact()}", locale: $locale),
            $this->translator->trans("tickets.priority.{$ticket->getPriority()}", locale: $locale),
            $this->exportUser($ticket->getRequester(), exportEmail: $isAgent),
            $this->exportUser($ticket->getAssignee(), exportEmail: $isAgent),
            $this->sanitizeField($ticket->getTeam()?->getName() ?? ''),
            implode("\n", $observers),
            $this->sanitizeField($organization?->getName() ?? ''),
            $solutionLabel,
            $canSeeContracts ? implode("\n", $contracts) : '',
            $canSeeRealTimeSpent ? $ticket->getSumTimeSpent('real') : '',
            $canSeeAccountedTimeSpent ? $ticket->getSumTimeSpent('accounted') : '',
            ($canSeeRealTimeSpent && $canSeeAccountedTimeSpent) ? $ticket->getSumTimeSpent('unaccounted') : '',
            implode("\n", $labels),
        ], escape: '');
    }

    public function exportUser(?Entity\User $user, bool $exportEmail): string
    {
        if (!$user) {
            return '';
        }

        $name = $user->getDisplayName();
        $email = $user->getEmail();

        if ($exportEmail && $name !== $email) {
            $name = "{$name} ({$email})";
        }

        return $this->sanitizeField($name);
    }

    /**
     * @template TField of string|int|null
     *
     * @param TField $field
     * @return TField
     */
    private function sanitizeField(mixed $field): mixed
    {
        if (
            !$field ||
            is_numeric($field)
        ) {
            return $field;
        }

        while ($field && in_array($field[0], ['=', '+', '-', '@', "\t", "\r", '%', '|'])) {
            $field = substr($field, 1);
        }

        return $field;
    }
}
