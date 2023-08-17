<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Entity;

use App\Entity\MessageDocument;
use PHPUnit\Framework\TestCase;

class MessageDocumentTest extends TestCase
{
    public function testIsMimetypeAcceptedAcceptsPdf(): void
    {
        $mimetype = 'application/pdf';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertTrue($result);
    }

    public function testIsMimetypeAcceptedAcceptsImages(): void
    {
        $mimetype = 'image/png';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertTrue($result);
    }

    public function testIsMimetypeAcceptedAcceptsText(): void
    {
        $mimetype = 'text/plain';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertTrue($result);
    }

    public function testIsMimetypeAcceptedRefusesExe(): void
    {
        $mimetype = 'application/vnd.microsoft.portable-executable';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertFalse($result);
    }

    public function testIsMimetypeAcceptedRefusesOctetStream(): void
    {
        $mimetype = 'application/octet-stream';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertFalse($result);
    }

    public function testIsMimetypeAcceptedRefusesQuicktimeVideo(): void
    {
        $mimetype = 'video/quicktime';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertFalse($result);
    }

    public function testIsMimetypeAcceptedRefusesMimeWithoutSubtype(): void
    {
        $mimetype = 'text';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertFalse($result);
    }

    public function testIsMimetypeAcceptedRefusesEmptyString(): void
    {
        $mimetype = '';

        $result = MessageDocument::isMimetypeAccepted($mimetype);

        $this->assertFalse($result);
    }
}
