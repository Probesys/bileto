<?php

namespace App\Service;

use App\ActivityMonitor;
use App\Entity;
use App\Repository;
use App\Security;
use App\TicketActivity;
use App\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TicketService
{
    private Entity\Organization $organization;

    public function __construct(
        private ActorsLister $actorsLister,
        private LabelSorter $labelSorter,
        private TeamSorter $teamSorter,
        private ActivityMonitor\ActiveUser $activeUser,
        private Repository\TicketRepository $ticketRepository,
        private Repository\MessageRepository $messageRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Security\Authorizer $authorizer,
        private HtmlSanitizerInterface $appMessageSanitizer,
        private EventDispatcherInterface $eventDispatcher,
        private ValidatorInterface $validator,
    ) {
    }

    public function setOrganization(Entity\Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function getAllUsers(): array
    {
        return $this->actorsLister->findByOrganization($organization);
    }

    public function getTeams(): array
    {
        $teams = $this->teamRepository->findByOrganization($organization);
        $this->teamSorter->sort($teams);
        return $teams;
    }

    public function getAgents(): array
    {
        return $this->actorsLister->findByOrganization($organization, roleType: 'agent');
    }

    public function getLabels(): array
    {
        $labels = $this->labelRepository->findAll();
        $this->labelSorter->sort($labels);
        return $labels;
    }

    /**
     * @param Entity\Label[] $labels
     */
    public function createTicket(
        Entity\User $user,
        string $title,
        string $content,
        ?Entity\User $requester = null,
        ?Entity\Team $team = null,
        ?Entity\User $assignee = null,
        string $type = Entity\Ticket::DEFAULT_TYPE,
        string $via = Entity\Message::DEFAULT_VIA,
        string $urgency = Entity\Ticket::DEFAULT_WEIGHT,
        string $impact = Entity\Ticket::DEFAULT_WEIGHT,
        string $priority = Entity\Ticket::DEFAULT_WEIGHT,
        array $labels = [],
        bool $isResolved = false,
    ): Entity\Ticket {
        if (!$this->authorizer->isGrantedToUser($user, 'orga:create:tickets', $this->organization)) {
            throw TicketServiceException::cannotCreateTicketError();
        }

        if (!$this->authorizer->isGrantedToUser($user, 'orga:update:tickets:actors', $this->organization)) {
            $requester = $user;
            $assignee = null;
            $team = null;
        }

        if (!$requester) {
            $requester = $user;
        }

        if (!$this->authorizer->isGrantedToUser($user, 'orga:update:tickets:type', $this->organization)) {
            $type = Entity\Ticket::DEFAULT_TYPE;
        }

        if (!$this->authorizer->isGrantedToUser($user, 'orga:update:tickets:priority', $this->organization)) {
            $urgency = Entity\Ticket::DEFAULT_WEIGHT;
            $impact = Entity\Ticket::DEFAULT_WEIGHT;
            $priority = Entity\Ticket::DEFAULT_WEIGHT;
        }

        if (!$this->authorizer->isGrantedToUser($user, 'orga:update:tickets:labels', $this->organization)) {
            $labels = [];
        }

        if (!$this->authorizer->isGrantedToUser($user, 'orga:update:tickets:status', $this->organization)) {
            $isResolved = false;
        }

        $ticket = new Entity\Ticket();
        $ticket->setTitle($title);
        $ticket->setType($type);
        $ticket->setUrgency($urgency);
        $ticket->setImpact($impact);
        $ticket->setPriority($priority);
        $ticket->setOrganization($this->organization);
        $ticket->setRequester($requester);
        $ticket->setTeam($team);
        $ticket->setLabels($labels);

        $errors = $this->validator->validate($ticket);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $content = $this->appMessageSanitizer->sanitize($content);

        $message = new Entity\Message();
        $message->setContent($content);
        $message->setTicket($ticket);
        $message->setIsConfidential(false);
        $message->setVia($via);

        $errors = $this->validator->validate($message);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->activeUser->change($user);

        $this->ticketRepository->save($ticket, true);
        $this->messageRepository->save($message, true);

        $ticketEvent = new TicketActivity\TicketEvent($ticket);
        $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::CREATED);

        $messageEvent = new TicketActivity\MessageEvent($message);
        $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

        if ($assignee) {
            $ticket->setAssignee($assignee);
            $this->ticketRepository->save($ticket, true);

            $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::ASSIGNED);
        }

        if ($isResolved) {
            $ticket->setStatus('resolved');
            $this->ticketRepository->save($ticket, true);

            $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::RESOLVED);
        }

        $this->activeUser->change(null);

        return $ticket;
    }
}
