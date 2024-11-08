<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ManifestController extends AbstractController
{
    #[Route('/manifest.webmanifest', name: 'web_manifest', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $response = $this->render('manifest.webmanifest.twig');
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }
}
