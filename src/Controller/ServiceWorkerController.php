<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class ServiceWorkerController extends AbstractController
{
    private const OFFLINE_TILE = 'offline-tile.';

    public function __construct(
        private readonly string $publicDirectory,
        private readonly string $projectBaseUrl,
    ) {
    }

    public function __invoke(#[MapQueryParameter] string $cacheName): Response
    {
        $finder = new Finder();
        $offlineTile = self::OFFLINE_TILE . 'png';
        $resolvedFiles = [
            "$this->projectBaseUrl/android-chrome-192x192.png",
            "$this->projectBaseUrl/android-chrome-512x512.png",
            "$this->projectBaseUrl/favicon.ico",
        ];
        $projectBaseUrlRegex = preg_quote($this->projectBaseUrl, '/');
        $files = $finder->in($this->publicDirectory . '/build')->name('/\.(jpe?g|js|css|svg|gif|png)$/')->files();
        foreach ($files as $file) {
            $pathname = $file->getRelativePathname();
            if (str_contains($pathname, '/photos/')) {
                continue;
            }
            if (str_contains($pathname, self::OFFLINE_TILE)) {
                $offlineTile = "$this->projectBaseUrl/build/$pathname";
            }
            $resolvedFiles[] = "$this->projectBaseUrl/build/$pathname";
        }

        $response = $this->render('service-worker.js.twig', [
            'files' => $resolvedFiles,
            'offlineTile' => $offlineTile,
            'projectBaseUrlRegex' => $projectBaseUrlRegex,
        ]);
        $response->headers->set('Content-Type', 'application/javascript');

        return $response;
    }
}
