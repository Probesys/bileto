<?php

// This file is part of Bileto.
// Copyright 2025 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Api;

use App\ActivityMonitor;
use App\Controller\BaseController;
use App\Entity;
use App\Repository;
use App\Service;
use App\TicketActivity;
use App\Utils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class TicketsController extends BaseController
{
    #[Route('/api/tickets', name: '[api] new ticket', methods: ['POST'])]
    public function new(
        Request $request,
        ActivityMonitor\ActiveUser $activeUser,
        Repository\OrganizationRepository $organizationRepository,
        Repository\MessageRepository $messageRepository,
        Repository\TicketRepository $ticketRepository,
        Repository\UserRepository $userRepository,
        Service\TicketAssigner $ticketAssigner,
        Service\UserCreator $userCreator,
        Service\UserService $userService,
        HtmlSanitizerInterface $appMessageSanitizer,
        EventDispatcherInterface $eventDispatcher,
        #[Autowire(env: 'APP_API_TOKEN')]
        string $apiToken,
    ): JsonResponse {
        if ($apiToken === '') {
            throw $this->createNotFoundException();
        }

        $authorizationHeader = $request->headers->get('authorization');

        $result = preg_match('/^Bearer (?P<token>[\w\-]+)$/', $authorizationHeader, $matches);
        if ($result !== 1) {
            return new JsonResponse([
                'error' => ['Invalid authorization header.'],
            ], status: Response::HTTP_UNAUTHORIZED);
        }

        $token = $matches['token'];

        if (!hash_equals($apiToken, $token)) {
            return new JsonResponse([
                'errors' => ['Invalid authorization token.'],
            ], status: Response::HTTP_UNAUTHORIZED);
        }

        $content = $request->getContent();
        $data = json_decode($content, associative: true);

        if (!is_array($data)) {
            return new JsonResponse([
                'errors' => ['Data is not valid Json.'],
            ], status: Response::HTTP_BAD_REQUEST);
        }

        $errors = [];

        $from = $data['from'] ?? null;
        $title = $data['title'] ?? null;
        $messageContent = $data['content'] ?? null;

        if (!is_string($from)) {
            $errors[] = "'from' field is invalid.";
        }

        if (!is_string($title)) {
            $errors[] = "'title' field is invalid.";
        }

        if (!is_string($messageContent)) {
            $errors[] = "'content' field is invalid.";
        }

        if ($errors) {
            return new JsonResponse([
                'errors' => $errors,
            ], status: Response::HTTP_BAD_REQUEST);
        }

        $senderEmail = $from;

        $domain = Utils\Email::extractDomain($senderEmail);
        $domainOrganization = $organizationRepository->findOneByDomainOrDefault($domain);

        $requester = $userRepository->findOneBy([
            'email' => $senderEmail,
        ]);

        if (!$requester && $domainOrganization) {
            try {
                $requester = $userCreator->create(email: $senderEmail);
            } catch (Service\UserCreatorException $e) {
                $errors = Utils\ConstraintErrorsFormatter::format($e->getErrors());

                return new JsonResponse([
                    'errors' => $errors,
                ], status: Response::HTTP_BAD_REQUEST);
            }
        } elseif (!$requester) {
            return new JsonResponse([
                'errors' => ['Unknown sender.'],
            ], status: Response::HTTP_BAD_REQUEST);
        }

        $requesterOrganization = $userService->getDefaultOrganization($requester);

        if (!$requesterOrganization) {
            return new JsonResponse([
                'errors' => ['Sender has not permission to create tickets.'],
            ], status: Response::HTTP_BAD_REQUEST);
        }

        $activeUser->change($requester);

        $ticket = new Entity\Ticket();
        $ticket->setTitle($title);
        $ticket->setOrganization($requesterOrganization);
        $ticket->setRequester($requester);

        $responsibleTeam = $ticketAssigner->getDefaultResponsibleTeam($requesterOrganization);
        $ticket->setTeam($responsibleTeam);

        $ticketRepository->save($ticket, true);

        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        $message = new Entity\Message();
        $message->setContent($messageContent);
        $message->setTicket($ticket);
        $message->setIsConfidential(false);
        $message->setVia('api');

        $messageRepository->save($message, true);

        $ticketEvent = new TicketActivity\TicketEvent($ticket);
        $eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::CREATED);

        $messageEvent = new TicketActivity\MessageEvent($message);
        $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

        $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED_ANSWER);

        $activeUser->change(null);

        return new JsonResponse([
            'message' => 'ok',
        ]);
    }
}
