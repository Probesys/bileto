<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController
{
    #[Route('/', name: 'home', methods: ['GET', 'HEAD'])]
    public function show(): Response
    {
        return new Response(
            '<html><body><p>Hello ProbeSuite!</p></body></html>'
        );
    }
}
