<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Entity;

interface MetaEntityInterface
{
    public function getUid(): ?string;

    public function setUid(string $uid): self;

    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): self;

    public function getCreatedBy(): ?User;

    public function setCreatedBy(?User $createdBy): self;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self;

    public function getUpdatedBy(): ?User;

    public function setUpdatedBy(?User $updatedBy): self;
}
