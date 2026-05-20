<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service;
use App\Tests;
use App\Tests\Factory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketCsvExporterTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;

    private KernelBrowser $client;
    private Service\TicketCsvExporter $ticketCsvExporter;

    #[Before]
    public function setupTest(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        /** @var Service\TicketCsvExporter */
        $ticketCsvExporter = $container->get(Service\TicketCsvExporter::class);
        $this->ticketCsvExporter = $ticketCsvExporter;
    }

    public function testStreamWritesEnglishHeader(): void
    {
        $content = $this->renderCsv([], 'en_GB');

        $this->assertStringContainsString('ID', $content);
        $this->assertStringContainsString('Reference', $content);
        $this->assertStringContainsString('Created at', $content);
        $this->assertStringContainsString('Created by', $content);
        $this->assertStringContainsString('Time spent (minutes)', $content);
        $this->assertStringContainsString('Labels', $content);
    }

    public function testStreamFormatsDatesAsYmdHi(): void
    {
        $ticket = Factory\TicketFactory::createOne([
            'createdAt' => new \DateTimeImmutable('2026-01-15 10:30:45'),
        ])->_real();

        $content = $this->renderCsv([$ticket], 'en_GB');

        $this->assertStringContainsString('2026-01-15 10:30', $content);
        $this->assertStringNotContainsString('10:30:45', $content);
    }

    public function testStreamSumsTimeSpent(): void
    {
        $user = Factory\UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, [
            'orga:see',
            'orga:see:tickets:contracts',
            'orga:see:tickets:time_spent:real',
            'orga:see:tickets:time_spent:accounted',
        ], $organization);
        $ticket = Factory\TicketFactory::createOne([
            'organization' => $organization,
        ])->_real();
        $contract = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ])->_real();
        Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $contract,
            'time' => 30,
            'realTime' => 30,
        ]);
        Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $contract,
            'time' => 30,
            'realTime' => 30,
        ]);
        Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => null,
            'time' => 30,
            'realTime' => 30,
        ]);

        $content = $this->renderCsv([$ticket], 'en_GB');

        $rows = $this->parseCsv($content);
        $this->assertSame('90', $rows[1][19]);
        $this->assertSame('60', $rows[1][20]);
        $this->assertSame('30', $rows[1][21]);
    }

    public function testStreamJoinsObserversLabelsAndContractsByNewline(): void
    {
        $user = Factory\UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, [
            'orga:see',
            'orga:see:tickets:contracts',
        ], $organization);
        $observer1 = Factory\UserFactory::createOne(['email' => 'obs1@example.com'])->_real();
        $observer2 = Factory\UserFactory::createOne(['email' => 'obs2@example.com'])->_real();
        $observer3 = Factory\UserFactory::createOne(['email' => 'obs3@example.com'])->_real();
        $label1 = Factory\LabelFactory::createOne(['name' => 'label-alpha'])->_real();
        $label2 = Factory\LabelFactory::createOne(['name' => 'label-beta'])->_real();
        $contract1 = Factory\ContractFactory::createOne([
            'name' => 'contract-alpha',
            'organization' => $organization,
        ])->_real();
        $contract2 = Factory\ContractFactory::createOne([
            'name' => 'contract-beta',
            'organization' => $organization,
        ])->_real();
        $ticket = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'observers' => [$observer1, $observer2, $observer3],
            'labels' => [$label1, $label2],
            'contracts' => [$contract1, $contract2],
        ])->_real();

        $content = $this->renderCsv([$ticket], 'en_GB');

        $rows = $this->parseCsv($content);
        $this->assertSame("obs1@example.com\nobs2@example.com\nobs3@example.com", $rows[1][15]);
        $this->assertSame("contract-alpha\ncontract-beta", $rows[1][18]);
        $this->assertSame("label-alpha\nlabel-beta", $rows[1][22]);
    }

    public function testStreamRendersSolutionYesNo(): void
    {
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $ticketWithSolution = Factory\TicketFactory::createOne([
            'organization' => $organization,
        ])->_real();
        $solution = Factory\MessageFactory::createOne([
            'ticket' => $ticketWithSolution,
        ])->_real();
        $ticketWithSolution->setSolution($solution);
        $ticketWithoutSolution = Factory\TicketFactory::createOne([
            'organization' => $organization,
        ])->_real();

        $content = $this->renderCsv([$ticketWithSolution, $ticketWithoutSolution], 'en_GB');

        $rows = $this->parseCsv($content);
        $this->assertSame('Yes', $rows[1][17]);
        $this->assertSame('No', $rows[2][17]);
    }

    public function testStreamLeavesPermissionedColumnsEmpty(): void
    {
        $user = Factory\UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see'], $organization);
        $contract = Factory\ContractFactory::createOne(['organization' => $organization])->_real();
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'organization' => $organization,
            'contracts' => [$contract],
        ])->_real();
        Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'time' => 30,
            'realTime' => 30,
        ]);

        $content = $this->renderCsv([$ticket], 'en_GB');

        $rows = $this->parseCsv($content);
        $this->assertSame('My ticket', $rows[1][8]);
        $this->assertSame('', $rows[1][18]);
        $this->assertSame('', $rows[1][19]);
        $this->assertSame('', $rows[1][20]);
        $this->assertSame('', $rows[1][21]);
    }

    /**
     * @param iterable<\App\Entity\Ticket> $tickets
     */
    private function renderCsv(iterable $tickets, string $locale): string
    {
        $callback = $this->ticketCsvExporter->stream($tickets, $locale);
        ob_start();
        $callback();
        return (string) ob_get_clean();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            /** @var array<int, string> $row */
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }
}
