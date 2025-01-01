<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    /** @return array<mixed> */
    #[Route('/', name: 'home', methods: ['GET'])]
    #[Template('home/index.html.twig')]
    public function home(): array
    {
        return [];
    }

    #[Route('/help', name: 'help', methods: ['GET'])]
    public function help(): never
    {
        throw new NotFoundHttpException();
    }
}
