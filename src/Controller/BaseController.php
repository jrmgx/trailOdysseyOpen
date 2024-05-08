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
            $tiles = $trip->getTiles()->filter(fn (Tiles $tiles) => $tiles->isPublic())->toArray();
            if (0 === \count($tiles)) {
                $tiles = [$trip->getTiles()->first()];
            }
        } else {
            $tiles = $trip->getTiles()->toArray();
        }

        return $this->serializer->serialize(array_values($tiles), JsonEncoder::FORMAT, ['groups' => ['leaflet']]);
    }

    protected function getUrls(Trip $trip): string
    {
        $params = ['lat' => '_LAT_', 'lon' => '_LON_', 'trip' => $trip->getId()];

        return (string) json_encode([
            'segmentNew' => $this->generateUrl('segment_new', $params),
            'segmentSplit' => $this->generateUrl('segment_split', $params + ['id' => 0]),
            'stageNew' => $this->generateUrl('stage_new', $params),
            'stageMove' => $this->generateUrl('stage_move', $params + ['id' => 0]),
            'interestNew' => $this->generateUrl('interest_new', $params),
            'interestMove' => $this->generateUrl('interest_move', $params + ['id' => 0]),
            'diaryEntryNew' => $this->generateUrl('diaryEntry_new', $params),
            'diaryEntryMove' => $this->generateUrl('diaryEntry_move', $params + ['id' => 0]),
            'diaryUpdateProgress' => $this->generateUrl('diaryEntry_update_progress', $params),
            'photoNew' => $this->generateUrl('photo_new', $params),
            'geoElements' => $this->generateUrl('geo_elements', $params),
            'mapOption' => $this->generateUrl('trip_edit_map_option', ['trip' => $trip->getId()]),
            'liveShowStage' => $this->generateUrl('live_show_stage', ['trip' => $trip->getId(), 'stage' => 0]),
        ]);
    }
}
