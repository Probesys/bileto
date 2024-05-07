<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\TeamAuthorization;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamAuthorization>
 *
 * @method TeamAuthorization|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeamAuthorization|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeamAuthorization[]    findAll()
 * @method TeamAuthorization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeamAuthorizationRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<TeamAuthorization> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamAuthorization::class);
    }
}
