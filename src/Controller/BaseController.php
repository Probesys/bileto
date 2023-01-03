<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\ConstraintViolationListInterface;

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

    protected function csrfError(): TranslatableMessage
    {
        return new TranslatableMessage('Invalid CSRF token.', [], 'security');
    }

    /**
     * @return array<string, string>
     */
    protected function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $property = $error->getPropertyPath();
            if (isset($formattedErrors[$property])) {
                $formattedErrors[$property] = implode(
                    ' ',
                    [$formattedErrors[$property], $error->getMessage()],
                );
            } else {
                $formattedErrors[$property] = $error->getMessage();
            }
        }
        return $formattedErrors;
    }
}
