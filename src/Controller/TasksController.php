<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;

class TasksController extends BaseController
{
    public function __construct(
        private readonly Repository\TaskRepository $taskRepository,
    ) {
    }

    #[Route('/tasks/{uid:task}.ics', name: 'task ics', methods: ['GET'])]
    public function ics(Entity\Task $task): Response
    {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:see:tickets:tasks', $ticket);

        $uid = $task->getUid();
        $label = $task->getLabel() ?? '';
        $createdAt = $task->getStartAt();
        $startAt = $task->getStartAt();
        $endAt = $task->getEndAt();

        if ($startAt === null || $endAt === null) {
            throw $this->createNotFoundException();
        }

        $timezoneName = date_default_timezone_get();
        $timezone = new \DateTimeZone($timezoneName);

        $dtStamp = $createdAt->setTimezone($timezone)->format('Ymd\THis');
        $dtStart = $startAt->setTimezone($timezone)->format('Ymd\THis');
        $dtEnd = $endAt->setTimezone($timezone)->format('Ymd\THis');

        $summary = str_replace(["\r", "\n", "\\"], ['', '', '\\\\'], $label);

        $icsContent = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Bileto//Bileto//EN',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP;TZID={$timezoneName}:{$dtStamp}",
            "DTSTART;TZID={$timezoneName}:{$dtStart}",
            "DTEND;TZID={$timezoneName}:{$dtEnd}",
            "SUMMARY:{$summary}",
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $filename = "task-{$uid}.ics";

        return new Response(
            $icsContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ],
        );
    }

    #[Route('/tasks/{uid:task}/edit', name: 'edit task', methods: ['GET', 'POST'])]
    public function edit(Entity\Task $task, Request $request): Response
    {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:tasks', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('task', Form\TaskForm::class, $task);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->taskRepository->save($task, flush: true);

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tasks/edit.html.twig', [
            'task' => $task,
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/tasks/{uid:task}/finish', name: 'finish task', methods: ['POST'])]
    public function finish(Entity\Task $task, Request $request): Response
    {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:tasks', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        if ($this->isCsrfTokenValid('finish task', $request->request->getString('_token'))) {
            $task->finish();
            $this->taskRepository->save($task, flush: true);
        } else {
            $this->addFlash('error', new TranslatableMessage('csrf.invalid', [], 'errors'));
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }

    #[Route('/tasks/{uid:task}/deletion', name: 'delete task', methods: ['POST'])]
    public function delete(Entity\Task $task, Request $request): Response
    {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:tasks', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        if ($this->isCsrfTokenValid('delete task', $request->request->getString('_token'))) {
            $this->taskRepository->remove($task, flush: true);
        } else {
            $this->addFlash('error', new TranslatableMessage('csrf.invalid', [], 'errors'));
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
