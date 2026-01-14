<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Ticket;

use App\Entity;
use App\Repository;
use App\SearchEngine;
use App\Utils;
use Doctrine\ORM;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends SearchEngine\QueryBuilder<Entity\Ticket>
 */
class QueryBuilder extends SearchEngine\QueryBuilder
{
    public function __construct(
        protected Repository\LabelRepository $labelRepository,
        protected Repository\OrganizationRepository $organizationRepository,
        protected Repository\TeamRepository $teamRepository,
        protected Repository\UserRepository $userRepository,
        protected Security $security,
        protected ORM\EntityManagerInterface $entityManager,
    ) {
    }

    protected function getFrom(): string
    {
        return Entity\Ticket::class;
    }

    protected function getFromAlias(): string
    {
        return 't';
    }

    protected function getTextField(): string
    {
        return 'title';
    }

    protected function getQualifiersMapping(): array
    {
        return [
            'status' => $this->buildStatusExpr(...),
            'assignee' => $this->buildAssigneeExpr(...),
            'requester' => $this->buildRequesterExpr(...),
            'observer' => $this->buildObserverExpr(...),
            'team' => $this->buildTeamExpr(...),
            'involves' => $this->buildInvolvesExpr(...),
            'org' => $this->buildOrganizationExpr(...),
            'uid' => $this->buildUidExpr(...),
            'type' => $this->buildTypeExpr(...),
            'urgency' => $this->buildUrgencyExpr(...),
            'impact' => $this->buildImpactExpr(...),
            'priority' => $this->buildPriorityExpr(...),
            'contract' => $this->buildContractExpr(...),
            'label' => $this->buildLabelExpr(...),
            'no:assignee' => $this->buildNoAssigneeExpr(...),
            'no:team' => $this->buildNoTeamExpr(...),
            'no:solution' => $this->buildNoSolutionExpr(...),
            'no:contract' => $this->buildNoContractExpr(...),
            'no:label' => $this->buildNoLabelExpr(...),
            'has:assignee' => $this->buildHasAssigneeExpr(...),
            'has:team' => $this->buildHasTeamExpr(...),
            'has:solution' => $this->buildHasSolutionExpr(...),
            'has:contract' => $this->buildHasContractExpr(...),
            'has:label' => $this->buildHasLabelExpr(...),
        ];
    }

    /**
     * @return literal-string
     */
    protected function buildStatusExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), function (mixed $status): array {
            if ($status === 'open') {
                return Entity\Ticket::OPEN_STATUSES;
            } elseif ($status === 'finished') {
                return Entity\Ticket::FINISHED_STATUSES;
            } else {
                return [$status];
            }
        });

        return $this->buildExpr('t.status', $value, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildAssigneeExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), $this->processActorValue(...));
        return $this->buildExpr('COALESCE(IDENTITY(t.assignee), 0)', $value, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildRequesterExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), $this->processActorValue(...));
        return $this->buildExpr('COALESCE(IDENTITY(t.requester), 0)', $value, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildObserverExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), $this->processActorValue(...));

        $observersDql = $this->getManyToManyDql(Entity\Ticket::class, 'observers', $value);

        if ($condition->not()) {
            return "t.id NOT IN ({$observersDql})";
        } else {
            return "t.id IN ({$observersDql})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildTeamExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), $this->processTeamValue(...));
        return $this->buildExpr('COALESCE(IDENTITY(t.team), 0)', $value, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildInvolvesExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), $this->processActorValue(...));

        $assigneeWhere = $this->buildExpr('COALESCE(IDENTITY(t.assignee), 0)', $value, false);

        $requesterWhere = $this->buildExpr('COALESCE(IDENTITY(t.requester), 0)', $value, false);

        $teamDql = $this->getManyToManyDql(Entity\Team::class, 'agents', $value);
        $teamWhere = "COALESCE(IDENTITY(t.team), 0) IN ({$teamDql})";

        $observersDql = $this->getManyToManyDql(Entity\Ticket::class, 'observers', $value);
        $observersWhere = "t.id IN ({$observersDql})";

        $where = "{$assigneeWhere} OR {$requesterWhere} OR {$teamWhere} OR {$observersWhere}";

        if ($condition->not()) {
            return "NOT ({$where})";
        } else {
            return "({$where})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildOrganizationExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), function (mixed $value): array {
            $id = $this->extractId($value);

            if ($id !== null) {
                return [$id];
            }

            $organizations = $this->organizationRepository->findLike($value);

            $ids = array_map(function (Entity\Organization $orga): int {
                return $orga->getId();
            }, $organizations);

            if ($ids) {
                return $ids;
            } else {
                return [-1];
            }
        });

        return $this->buildExpr('t.organization', $value, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildUidExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.uid', $condition->getValue(), $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildTypeExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.type', $condition->getValue(), $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildUrgencyExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.urgency', $condition->getValue(), $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildImpactExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.impact', $condition->getValue(), $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildPriorityExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.priority', $condition->getValue(), $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildContractExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), function (mixed $value): array {
            $id = $this->extractId($value);

            if ($id) {
                return [$id];
            } else {
                return [-1];
            }
        });

        $contractsDql = $this->getManyToManyDql(Entity\Ticket::class, 'contracts', $value);

        if ($condition->not()) {
            return "t.id NOT IN ({$contractsDql})";
        } else {
            return "t.id IN ({$contractsDql})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildLabelExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), function (mixed $value): array {
            $labels = $this->labelRepository->findByName($value);

            $ids = array_map(function (Entity\Label $label): int {
                return $label->getId();
            }, $labels);

            if ($ids) {
                return $ids;
            } else {
                return [-1];
            }
        });

        $labelsDql = $this->getManyToManyDql(Entity\Ticket::class, 'labels', $value);

        if ($condition->not()) {
            return "t.id NOT IN ({$labelsDql})";
        } else {
            return "t.id IN ({$labelsDql})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildNoAssigneeExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.assignee', null, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildNoTeamExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.team', null, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildNoSolutionExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.solution', null, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildNoContractExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildEmptyExpr('t.contracts', $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildNoLabelExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildEmptyExpr('t.labels', $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildHasAssigneeExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.assignee', null, !$condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildHasTeamExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.team', null, !$condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildHasSolutionExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildExpr('t.solution', null, !$condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildHasContractExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildEmptyExpr('t.contracts', !$condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildHasLabelExpr(SearchEngine\Query\Condition $condition): string
    {
        return $this->buildEmptyExpr('t.labels', !$condition->not());
    }

    /**
     * @return mixed[]
     */
    protected function processActorValue(mixed $value): array
    {
        $id = $this->extractId($value);

        if ($id !== null) {
            return [$id];
        }

        if ($value === '@me') {
            /** @var Entity\User */
            $currentUser = $this->security->getUser();
            return [$currentUser->getId()];
        }

        $users = $this->userRepository->findLike($value);

        $ids = array_map(function (Entity\User $user): int {
            return $user->getId();
        }, $users);

        if ($ids) {
            return $ids;
        } else {
            return [-1];
        }
    }

    /**
     * @return mixed[]
     */
    protected function processTeamValue(mixed $value): array
    {
        $id = $this->extractId($value);

        if ($id !== null) {
            return [$id];
        }

        $teams = $this->teamRepository->findLike($value);

        $ids = array_map(function (Entity\Team $team): int {
            return $team->getId();
        }, $teams);

        if ($ids) {
            return $ids;
        } else {
            return [-1];
        }
    }
}
