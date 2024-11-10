<?php

namespace App\Controller;

use App\Entity\Tiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TilesProxyController extends AbstractController
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:130.0) Gecko/20100100 Firefox/130.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?Profiler $profiler,
    ) {
    }

    #[Cache(maxage: 86_400, public: true, mustRevalidate: true)]
    #[Route('/t/p/{id}/{x}/{y}/{z}', name: 'tiles_proxy_get', methods: ['GET'])]
    public function tilesProxyGet(Tiles $tiles, string $x, string $y, string $z): Response
    {
        $this->profiler?->disable();

        $url = str_replace(['{x}', '{y}', '{z}'], [$x, $y, $z], $tiles->getUrl());

        $headers = [];
        $headers['User-Agent'] = [self::USER_AGENT];
        if (str_contains($url, 'data.geopf.fr')) {
            $headers['Referer'] = 'https://www.geoportail.gouv.fr/';
        }

        $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);

        $responseHeaders = $response->getHeaders(false);
        $responseHeaders['content-encoding'] = [];
        try {
            if (404 === $response->getStatusCode()) {
                return new JsonResponse(['type' => 'FeatureCollection', 'features' => []]);
            }

            return new Response(
                $response->getContent(false),
                $response->getStatusCode(),
                $responseHeaders,
            );
        } catch (\Exception) {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    #[Route('/t/p/{id}/bbox/{bbox}', name: 'tiles_proxy_bbox_get', methods: ['GET'])]
    public function tilesProxyBboxGet(Tiles $tiles, string $bbox): Response
    {
        // $this->profiler?->disable();
        $url = $tiles->getUrl();

        $formData = new FormDataPart(['bbox' => $bbox]);
        $headers = array_merge($formData->getPreparedHeaders()->toArray(), [
            'User-Agent' => self::USER_AGENT,
        ]);

        $body = '';
        foreach ($formData->bodyToIterable() as $chunk) {
            $body .= $chunk;
        }

        $headers['Content-Length'] = mb_strlen($body);

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        $responseHeaders = $response->getHeaders(false);
        $responseHeaders['content-encoding'] = [];
        try {
            if (404 === $response->getStatusCode()) {
                return new JsonResponse(['type' => 'FeatureCollection', 'features' => []]);
            }

            return new Response(
                $response->getContent(false),
                $response->getStatusCode(),
                $responseHeaders,
            );
        } catch (\Exception) {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
