<?php

namespace App\Controller;

use App\Entity\Tiles;
use App\Entity\Trip;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

abstract class BaseController extends AbstractController
{
    public function __construct(
        protected SerializerInterface $serializer,
    ) {
    }

    protected function getOptions(Trip $trip): string
    {
        return (string) json_encode([
            'center' => ['lat' => $trip->getMapCenter()->getLat(), 'lon' => $trip->getMapCenter()->getLon()],
            'zoom' => $trip->getMapZoom(),
        ]);
    }

    protected function getTiles(Trip $trip, bool $publicOnly = false): string
    {
        if ($publicOnly) {
            $tiles = $trip->getTiles()->filter(fn (Tiles $tiles) => $tiles->getPublic())->toArray();
            if (0 === \count($tiles)) {
                $tiles = [$trip->getTiles()->first()];
            }
        } else {
            $tiles = $trip->getTiles()->toArray();
        }

        return $this->serializer->serialize(array_values($tiles), JsonEncoder::FORMAT, ['groups' => ['leaflet']]);
    }
}
