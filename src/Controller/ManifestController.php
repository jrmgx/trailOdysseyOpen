<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ManifestController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        $url = $request->query->get('url', './');
        $response = $this->render('manifest.webmanifest.twig', [
            'currentUrl' => $url,
        ]);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }
}
