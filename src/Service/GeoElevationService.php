<?php

namespace App\Service;

use App\Model\Point;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoElevationService
{
    private const SERVER = 'https://secure.geonames.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $geonameUsername = 'jrmgx',
    ) {
    }

    /**
     * Given an array of Point add elevation if missing.
     * Note: you can have 20 points maximum per call.
     *
     * @param array<int, Point> $points
     */
    public function getElevations(array $points): void
    {
        if (\count($points) > 20) {
            throw new \RuntimeException('Asked for too many points, 20 maximum.');
        }
        $points = array_filter($points, fn (Point $p) => !$p->hasElevation());
        if (0 === \count($points)) {
            return;
        }
        $lats = [];
        $lngs = [];
        /** @var Point $point */
        foreach ($points as $point) {
            $lats[] = $point->lat;
            $lngs[] = $point->lon;
        }
        $results = [];
        try {
            $results = $this->httpClient->request('GET', self::SERVER . '/srtm3JSON', [
                'query' => [
                    'lats' => implode(',', $lats),
                    'lngs' => implode(',', $lngs),
                    'username' => $this->geonameUsername,
                ],
            ])->toArray();
            $geonames = $results['geonames'];
            for ($i = 0; $i < \count($points); ++$i) {
                $point = $points[$i];
                $el = $geonames[$i]['srtm3'] ?? '';
                $point->el = $el;
            }
        } catch (\Exception $exception) {
            $this->logger->error('Error: GetElevations ' . $exception->getMessage() . ' Results: ' . json_encode($results));
        }
    }
}
