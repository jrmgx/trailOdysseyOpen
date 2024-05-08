<?php

namespace App\Service;

use App\Entity\MappableInterface;
use App\Helper\GeoHelper;
use App\Model\Point;
use App\Model\SearchElementResult;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoCodingService
{
    private const SERVERS = [
        // Too slow 'https://overpass.kumi.systems/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Given the address, return an array of suggestions using nominatim API.
     *
     * @see https://nominatim.org/release-docs/develop/api/Search/
     *
     * @return array<array{
     *     place_id: int,
     *     licence: string,
     *     osm_type: string,
     *     osm_id: int,
     *     boundingbox: array<string>,
     *     lat: string,
     *     lon: string,
     *     display_name: string,
     *     class: string,
     *     type: string,
     *     importance: float,
     *     address: array<string, string>
     * }>
     */
    public function suggestAddresses(string $address): array
    {
        $results = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'q' => $address,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 10,
            ],
        ])->toArray();
        $has = [];

        return array_filter($results, function (array $entry) use (&$has) {
            $name = $entry['display_name'];
            if (!\in_array($name, $has, true)) {
                $has[] = $name;

                return true;
            }

            return false;
        });
    }

    public function findPlaceFromPoint(Point $point): ?string
    {
        $data = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
            'query' => [
                'lat' => $point->lat,
                'lon' => $point->lon,
                'format' => 'json',
            ],
        ])->toArray();

        return $data['address']['village'] ??
            $data['address']['city'] ??
            $data['address']['municipality']
        ;
    }

    public function tryUpdatePointName(MappableInterface $mappable): void
    {
        try {
            $mappable->setPointName($this->findPlaceFromPoint($mappable->getPoint()->toPoint()) ?? '');
        } catch (\Exception $e) {
            $this->logger->error('GeoCodingService Error: ' . $e->getMessage());
        }
    }

    /**
     * @return array<SearchElementResult>
     */
    public function searchElements(Point $southWest, Point $northEast, string $key, string $value): array
    {
        $distanceInMeters = GeoHelper::calculateDistance($southWest, $northEast);
        if ($distanceInMeters > 300_000) { // 300 km is too much to ask
            $point = GeoHelper::midPoint($southWest, $northEast);

            return [
                SearchElementResult::fromError($point, 'The area is too big! Please Zoom in.'),
            ];
        }
        $keyValue = "\"$key\"=\"$value\"";
        $bounding = implode(',', [
            $southWest->lat, $southWest->lon,
            $northEast->lat, $northEast->lon,
        ]);
        $query = <<<QUERY
[out:json];
(
  node[$keyValue]($bounding);
  way[$keyValue]($bounding);
  relation[$keyValue]($bounding);
);
out center;
QUERY;
        $server = self::SERVERS[array_rand(self::SERVERS)];
        $results = $this->httpClient->request('GET', $server, [
            'query' => ['data' => $query],
        ])->toArray();

        if (!isset($results['elements'])) {
            return [];
        }

        if (\count($results['elements']) > 200) {
            $point = GeoHelper::midPoint($southWest, $northEast);

            return [
                SearchElementResult::fromError($point, 'Too many results! Please Zoom in.'),
            ];
        }

        return array_filter(array_map(
            fn (array $e) => SearchElementResult::fromElementResult($e, $key, $value),
            $results['elements']
        ));
    }
}
