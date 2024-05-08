<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    /** @return array<mixed> */
    #[Route('/help', name: 'help', methods: ['GET'])]
    #[Template('home/help.html.twig')]
    public function help(): array
    {
        return [];
    }
}
