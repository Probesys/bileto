<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Utils\Locales;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PagesController extends BaseController
{
    #[Route('/', name: 'home', methods: ['GET', 'HEAD'])]
    public function home(): Response
    {
        return $this->redirectToRoute('organizations');
    }

    #[Route('/about', name: 'about', methods: ['GET', 'HEAD'])]
    public function about(): Response
    {
        /** @var string $projectDir */
        $projectDir = $this->getParameter('kernel.project_dir');
        $versionPathfile = "{$projectDir}/VERSION.txt";
        $version = file_get_contents($versionPathfile);
        if (!$version) {
            $version = 'N/A';
        }

        return $this->render('pages/about.html.twig', [
            'version' => $version,
            'availableLanguages' => Locales::getSupportedLanguages(),
        ]);
    }

    #[Route('/app.manifest', name: 'webmanifest', methods: ['GET', 'HEAD'])]
    public function webmanifest(): Response
    {
        $response = $this->render('pages/webmanifest.json.twig');
        $response->headers->set('Content-Type', 'application/manifest+json');
        return $response;
    }
}
