<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Ticket;

use App\Entity;
use App\Repository;
use App\SearchEngine;
use Doctrine\Common\Collections;
use Symfony\Bundle\SecurityBundle\Security;

class QuickSearchFilterBuilder
{
    public function __construct(
        private Repository\LabelRepository $labelRepository,
        private Repository\UserRepository $userRepository,
        private Security $security,
    ) {
    }

    public function getFilter(?SearchEngine\Query $query = null): ?QuickSearchFilter
    {
        $quickSearchFilter = new QuickSearchFilter();

        if (!$query) {
            return $quickSearchFilter;
        }

        $qualifiersAlreadySet = [];

        foreach ($query->getConditions() as $condition) {
            if ($condition->isTextCondition()) {
                $value = $condition->getValue();

                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                if ($condition->not()) {
                    $value = "NOT {$value}";
                }

                if ($condition->or()) {
                    $value = "OR {$value}";
                }

                $quickSearchFilter->addText($value);
            } elseif (
                $condition->isQualifierCondition() &&
                $condition->and() &&
                !$condition->not()
            ) {
                $qualifier = $condition->getQualifier();
                $value = $condition->getValue();

                if ($qualifier !== 'label' && isset($qualifiersAlreadySet[$qualifier])) {
                    return null;
                }

                $qualifiersAlreadySet[$qualifier] = true;

                if (is_array($value)) {
                    $values = $value;
                } else {
                    $values = [$value];
                }

                if ($qualifier === 'status') {
                    $quickSearchFilter->setStatuses($values);
                } elseif ($qualifier === 'priority') {
                    $quickSearchFilter->setPriorities($values);
                } elseif ($qualifier === 'urgency') {
                    $quickSearchFilter->setUrgencies($values);
                } elseif ($qualifier === 'impact') {
                    $quickSearchFilter->setImpacts($values);
                } elseif ($qualifier === 'involves') {
                    $users = $this->processUsers($values);
                    $quickSearchFilter->setInvolves($users);
                } elseif ($qualifier === 'assignee') {
                    $users = $this->processUsers($values);
                    $quickSearchFilter->setAssignees($users);
                } elseif ($qualifier === 'requester') {
                    $users = $this->processUsers($values);
                    $quickSearchFilter->setRequesters($users);
                } elseif ($qualifier === 'label') {
                    foreach ($values as $value) {
                        $labels = $this->labelRepository->findByName($value);

                        foreach ($labels as $label) {
                            $quickSearchFilter->addLabel($label);
                        }
                    }
                } elseif ($qualifier === 'type' && count($values) === 1) {
                    $quickSearchFilter->setType($values[0]);
                } elseif ($qualifier === 'no' && $values[0] === 'assignee') {
                    $quickSearchFilter->setUnassignedOnly(true);
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $quickSearchFilter;
    }

    /**
     * @param string[] $values
     *
     * @return Collections\ArrayCollection<int, Entity\User>
     */
    private function processUsers(array $values): Collections\ArrayCollection
    {
        $ids = [];

        /** @var ?Entity\User */
        $currentUser = $this->security->getUser();

        foreach ($values as $value) {
            if (preg_match('/^#[\d]+$/', $value)) {
                $value = substr($value, 1);
                $ids[] = intval($value);
            } elseif ($value === '@me' && $currentUser) {
                $ids[] = $currentUser->getId();
            }
        }

        $users = $this->userRepository->findBy([
            'id' => $ids,
        ]);

        return new Collections\ArrayCollection($users);
    }
}
