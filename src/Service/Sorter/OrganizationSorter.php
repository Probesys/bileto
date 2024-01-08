<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Organization;

class OrganizationSorter extends LocaleSorter
{
    /**
     * @param Organization[] $organizations
     */
    public function sort(array &$organizations): void
    {
        uasort($organizations, function (Organization $o1, Organization $o2): int {
            $pathComparison = strcmp($o1->getParentsPath(), $o2->getParentsPath());

            if ($pathComparison !== 0) {
                return $pathComparison;
            }

            return $this->localeCompare($o1->getName(), $o2->getName());
        });
    }

    /**
     * @param Organization[] $organizations
     * @return Organization[]
     */
    public function asTree(array $organizations, int $maxDepth = 0): array
    {
        $organizationsIndexByIds = [];
        foreach ($organizations as $organization) {
            $organizationsIndexByIds[$organization->getId()] = $organization;
        }

        $this->sort($organizationsIndexByIds);

        $rootOrganizations = [];
        foreach ($organizationsIndexByIds as $organization) {
            $orgaDepth = $organization->getDepth();
            if ($maxDepth > 0 && $orgaDepth > $maxDepth) {
                continue;
            }

            $parentId = $organization->getParentOrganizationId();
            $parent = $organizationsIndexByIds[$parentId] ?? null;
            if ($parent) {
                $parent->addSubOrganization($organization);
            } else {
                $rootOrganizations[] = $organization;
            }
        }

        return $rootOrganizations;
    }
}
