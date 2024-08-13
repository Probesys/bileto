<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

class BaseController extends AbstractController
{
    /**
     * @param mixed[] $parameters
     */
    protected function renderBadRequest(string $view, array $parameters = [], Response $response = null): Response
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
     * @param array<string, mixed> $options
     */
    protected function createNamedForm(
        string $name,
        string $type,
        mixed $data = null,
        array $options = [],
    ): FormInterface {
        return $this->container->get('form.factory')->createNamed($name, $type, $data, $options);
    }
}
