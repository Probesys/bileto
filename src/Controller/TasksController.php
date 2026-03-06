<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class TasksController extends BaseController
{
    #[Route('/tasks/{uid:task}/edit', name: 'edit task', methods: ['GET', 'POST'])]
    public function edit(
        Entity\Task $task,
        Request $request,
        Repository\TaskRepository $taskRepository,
    ): Response {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:tasks', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('task', Form\TaskForm::class, $task);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $taskRepository->save($task, true);

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

    #[Route('/tasks/{uid:task}/delete', name: 'delete task', methods: ['POST'])]
    public function delete(
        Entity\Task $task,
        Request $request,
        Repository\TaskRepository $taskRepository,
    ): Response {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:tasks', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        if (!$this->isCsrfTokenValid('delete task', $request->request->getString('_delete_token'))) {
            $this->addFlash('error', new TranslatableMessage('csrf.invalid', [], 'errors'));

            return $this->redirectToRoute('edit task', [
                'uid' => $task->getUid(),
            ]);
        }

        $taskRepository->remove($task, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }

    #[Route('/tasks/{uid:task}/finish', name: 'finish task', methods: ['POST'])]
    public function finish(
        Entity\Task $task,
        Request $request,
        Repository\TaskRepository $taskRepository,
    ): Response {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:tasks', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        if (!$this->isCsrfTokenValid('finish task', $request->request->getString('_token'))) {
            $this->addFlash('error', new TranslatableMessage('csrf.invalid', [], 'errors'));

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $task->setFinishedAt(Utils\Time::now());
        $taskRepository->save($task, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }

    #[Route('/tasks/{uid:task}/ics', name: 'download task ics', methods: ['GET'])]
    public function ics(
        Entity\Task $task,
    ): Response {
        $ticket = $task->getTicket();

        $this->denyAccessUnlessGranted('orga:see:tickets:tasks', $ticket);

        $uid = $task->getUid();
        $label = $task->getLabel() ?? '';
        $startAt = $task->getStartAt();
        $endAt = $task->getEndAt();

        if ($startAt === null || $endAt === null) {
            throw $this->createNotFoundException();
        }

        $dtStamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $dtStart = $startAt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $dtEnd = $endAt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $summary = str_replace(["\r", "\n", "\\"], ['', '', '\\\\'], $label);

        $icsContent = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Bileto//Bileto//EN',
            'BEGIN:VEVENT',
            "UID:{$uid}@bileto",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
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
}
