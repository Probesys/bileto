<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

class BaseController extends AbstractController
{
    /**
     * @param mixed[] $parameters
     */
    protected function renderBadRequest(string $view, array $parameters = [], ?Response $response = null): Response
    {
        if ($response === null) {
            $response = new Response('', Response::HTTP_BAD_REQUEST);
        } else {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }

        return $this->render($view, $parameters, $response);
    }

    protected function isPathRedirectable(string $path): bool
    {
        $router = $this->container->get('router');

        try {
            $router->match($path);
            return true;
        } catch (MethodNotAllowedException $e) {
            return in_array('GET', $e->getAllowedMethods());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @template TData
     * @template TFormType of Form\FormTypeInterface<TData>
     *
     * @param class-string<TFormType> $type
     * @param TData $data
     * @param array<string, mixed> $options
     *
     * @return ($data is null ? Form\FormInterface<?TData> : Form\FormInterface<TData>)
     */
    protected function createNamedForm(
        string $name,
        string $type,
        mixed $data = null,
        array $options = [],
    ): Form\FormInterface {
        // PHPStan complains about template type TData on class
        // Symfony\Component\Form\FormInterface not being covariant. I'm not
        // comfortable with covariant problems to fix this issue. Also, I
        // assume that the problem comes from phpstan-symfony.
        // @see https://github.com/phpstan/phpstan-symfony/issues/363
        // @phpstan-ignore return.type
        return $this->container->get('form.factory')->createNamed($name, $type, $data, $options);
    }

    protected function denyAccessIfTicketIsClosed(
        Entity\Ticket $ticket,
        string $message = 'Access denied because ticket is closed.',
    ): void {
        if ($ticket->isClosed()) {
            throw $this->createAccessDeniedException($message);
        }
    }

    protected function denyAccessIfUserIsAnonymized(
        Entity\User $user,
        string $message = 'Access denied because user is anonymized.',
    ): void {
        if ($user->isAnonymized()) {
            throw $this->createAccessDeniedException($message);
        }
    }
}
