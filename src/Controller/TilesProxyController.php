<?php

namespace App\Controller;

use App\Entity\Tiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TilesProxyController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'tilesCache')]
        private readonly CacheInterface $tileCache,
        private readonly ?Profiler $profiler,
    ) {
    }

    public function __invoke(?Profiler $profiler, Tiles $tiles, string $x, string $y, string $z): Response
    {
        $this->profiler?->disable();

        $url = str_replace(['{x}', '{y}', '{z}'], [$x, $y, $z], $tiles->getUrl());
        $key = sha1($url);

        return $this->tileCache->get($key, function (ItemInterface $item) use ($url): Response {
            $headers = [];
            if (str_contains($url, 'data.geopf.fr')) {
                $headers['Referer'] = 'https://www.geoportail.gouv.fr/';
            }
            // $item->expiresAfter(); // framework.cache.default_lifetime should take prevalence
            $response = $this->httpClient->request('GET', $url, ['headers' => $headers]);
            try {
                return new Response(
                    $response->getContent(false),
                    $response->getStatusCode(),
                    $response->getHeaders(false)
                );
            } catch (\Exception) {
                $item->expiresAfter(0); // Do not cache

                return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
            }
        });
    }
}
