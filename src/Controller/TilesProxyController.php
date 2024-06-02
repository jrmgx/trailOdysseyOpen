<?php

namespace App\Controller;

use App\Entity\Tiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TilesProxyController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?Profiler $profiler,
    ) {
    }

    #[Cache(maxage: 86_400, public: true, mustRevalidate: true)]
    public function __invoke(?Profiler $profiler, Tiles $tiles, string $x, string $y, string $z): Response
    {
        $this->profiler?->disable();

        $url = str_replace(['{x}', '{y}', '{z}'], [$x, $y, $z], $tiles->getUrl());

        $headers = [];
        if (str_contains($url, 'data.geopf.fr')) {
            $headers['Referer'] = 'https://www.geoportail.gouv.fr/';
        }

        $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);
        try {
            return new Response(
                $response->getContent(false),
                $response->getStatusCode(),
                $response->getHeaders(false)
            );
        } catch (\Exception) {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
