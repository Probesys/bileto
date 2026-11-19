<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests;
use App\Tests\Factory;
use App\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the contract');
    }

    public function testGetEditOnlyOffersOngoingContractsFromTicketOrganization(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $contract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        Factory\ContractFactory::createOne([
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(2, 'weeks'),
            'endAt' => Utils\Time::ago(1, 'week'),
        ]);
        Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::fromNow(1, 'week'),
            'endAt' => Utils\Time::fromNow(2, 'weeks'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$contract],
        ]);

        $crawler = $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['', (string) $contract->getId()],
            $crawler->filter('#ticket_ongoing_contract_ongoingContract option')->extract(['value']),
        );
        $this->assertSame(
            (string) $contract->getId(),
            $crawler->filter('#ticket_ongoing_contract_ongoingContract option[selected]')->attr('value'),
        );
    }

    public function testGetEditPrefersContractFromMostRecentlyUpdatedMatchingTicket(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $preferredContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $olderContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $now = Utils\Time::now();

        try {
            foreach ([$preferredContract, $olderContract] as $index => $contract) {
                Utils\Time::freeze($now->modify('-' . ($index + 1) . ' days'));
                Factory\TicketFactory::createOne([
                    'organization' => $organization,
                    'requester' => $user,
                    'assignee' => $user,
                    'contracts' => [$contract],
                ]);
            }

            Utils\Time::freeze($now);
            $ticket = Factory\TicketFactory::createOne([
                'status' => 'in_progress',
                'organization' => $organization,
                'createdBy' => $user,
                'requester' => $user,
                'assignee' => $user,
            ]);

            $crawler = $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");

            $this->assertResponseIsSuccessful();
            $this->assertSame(
                (string) $preferredContract->getId(),
                $crawler->filter('#ticket_ongoing_contract_ongoingContract option[selected]')->attr('value'),
            );
            $ticket->_refresh();
            $this->assertCount(0, $ticket->getContracts());
        } finally {
            Utils\Time::unfreeze();
        }
    }

    #[DataProvider('unmatchedContractContexts')]
    public function testGetEditDoesNotPreferContractOutsideTicketContext(string $context): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $historyOrganization = $context === 'organization' ? Factory\OrganizationFactory::createOne() : $organization;
        $contract = Factory\ContractFactory::createOne([
            'organization' => $historyOrganization,
            'startAt' => $context === 'future' ? Utils\Time::fromNow(1, 'week') : Utils\Time::ago(2, 'weeks'),
            'endAt' => $context === 'expired' ? Utils\Time::ago(1, 'week') : Utils\Time::fromNow(2, 'weeks'),
        ]);
        Factory\TicketFactory::createOne([
            'organization' => $historyOrganization,
            'requester' => $context === 'requester' ? $otherUser : $user,
            'assignee' => $context === 'assignee' ? $otherUser : $user,
            'contracts' => [$contract],
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'requester' => $context === 'missing requester' ? null : $user,
            'assignee' => $context === 'missing assignee' ? null : $user,
        ]);

        $crawler = $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");

        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="ticket_ongoing_contract"]')->form();
        $this->assertSame('', $form->getPhpValues()['ticket_ongoing_contract']['ongoingContract']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unmatchedContractContexts(): iterable
    {
        yield 'organization' => ['organization'];
        yield 'requester' => ['requester'];
        yield 'assignee' => ['assignee'];
        yield 'missing requester' => ['missing requester'];
        yield 'missing assignee' => ['missing assignee'];
        yield 'expired' => ['expired'];
        yield 'future' => ['future'];
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testPostEditSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => $newContract->getId(),
                'includeUnaccountedTime' => false,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($newContract->getId(), $contracts[0]->getId());
    }

    public function testPostEditCanUnassignContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $preferredContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        Factory\TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $user,
            'contracts' => [$preferredContract],
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'requester' => $user,
            'assignee' => $user,
            'contracts' => [$oldContract],
        ]);

        $crawler = $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            (string) $oldContract->getId(),
            $crawler->filter('#ticket_ongoing_contract_ongoingContract option[selected]')->attr('value'),
        );

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => '',
                'includeUnaccountedTime' => false,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(0, count($contracts));
    }

    public function testPostEditCanDeclineSuggestedContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $contract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        Factory\TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $user,
            'contracts' => [$contract],
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'requester' => $user,
            'assignee' => $user,
        ]);
        $crawler = $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            (string) $contract->getId(),
            $crawler->filter('#ticket_ongoing_contract_ongoingContract option[selected]')->attr('value'),
        );

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => '',
                'includeUnaccountedTime' => false,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertCount(0, $ticket->getContracts());
    }

    public function testPostEditCanIncludeUnaccountedTimeSpent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);
        $timeSpentAccounted = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $oldContract,
        ]);
        $timeSpentNotAccounted = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => null,
            'time' => 15,
            'realTime' => 15,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => $newContract->getId(),
                'includeUnaccountedTime' => true,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($newContract->getId(), $contracts[0]->getId());
        $timeSpentAccounted->_refresh();
        $this->assertSame($oldContract->getId(), $timeSpentAccounted->getContract()->getId());
        $timeSpentNotAccounted->_refresh();
        $this->assertSame($newContract->getId(), $timeSpentNotAccounted->getContract()->getId());
        $this->assertSame(30, $timeSpentNotAccounted->getTime());
        $this->assertSame(15, $timeSpentNotAccounted->getRealTime());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => 'not the token',
                'ongoingContract' => $newContract->getId(),
            ],
        ]);

        $this->assertSelectorTextContains('#ticket_ongoing_contract-error', 'The security token is invalid');
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($oldContract->getId(), $contracts[0]->getId());
    }

    public function testPostEditRejectsMalformedContractId(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $contract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => "{$contract->getId()}suffix",
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('#ticket_ongoing_contract_ongoingContract[aria-invalid="true"]');
        $ticket->_refresh();
        $this->assertNull($ticket->getOngoingContract());
    }

    public function testPostEditRejectsContractsOutsideOrganizationOrValidityPeriod(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $otherOrganizationContract = Factory\ContractFactory::createOne([
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $expiredContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(2, 'weeks'),
            'endAt' => Utils\Time::ago(1, 'week'),
        ]);
        $futureContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::fromNow(1, 'week'),
            'endAt' => Utils\Time::fromNow(2, 'weeks'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
        ]);

        foreach ([$otherOrganizationContract, $expiredContract, $futureContract] as $contract) {
            $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
                'ticket_ongoing_contract' => [
                    '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                    'ongoingContract' => $contract->getId(),
                ],
            ]);

            $this->assertResponseStatusCodeSame(422);
            $this->assertSelectorExists('#ticket_ongoing_contract_ongoingContract[aria-invalid="true"]');
            $ticket->_refresh();
            $this->assertNull($ticket->getOngoingContract());
        }
    }
}
