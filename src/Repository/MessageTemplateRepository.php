<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\MessageTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageTemplate>
 */
class MessageTemplateRepository extends ServiceEntityRepository
{
    /** @use CommonTrait<MessageTemplate> */
    use CommonTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageTemplate::class);
    }
}
