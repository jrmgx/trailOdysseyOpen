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
        $latLon = ['lat' => '_LAT_', 'lon' => '_LON_'];
        $tripId = ['trip' => $trip->getId()];
        $id0 = ['id' => 0];

        return (string) json_encode([
            'segmentNew' => $this->generateUrl('segment_new', $tripId + $latLon),
            'segmentNewItinerary' => $this->generateUrl('segment_new_itinerary', $tripId),
            'segmentSplit' => $this->generateUrl('segment_split', $tripId + $latLon + $id0),
            'stageNew' => $this->generateUrl('stage_new', $tripId + $latLon),
            'stageMove' => $this->generateUrl('stage_move', $tripId + $latLon + $id0),
            'interestNew' => $this->generateUrl('interest_new', $tripId + $latLon),
            'interestMove' => $this->generateUrl('interest_move', $tripId + $latLon + $id0),
            'diaryEntryNew' => $this->generateUrl('diaryEntry_new', $tripId + $latLon),
            'diaryEntryMove' => $this->generateUrl('diaryEntry_move', $tripId + $latLon + $id0),
            'diaryUpdateProgress' => $this->generateUrl('diaryEntry_update_progress', $tripId + $latLon),
            'photoNew' => $this->generateUrl('photo_new', $tripId + $latLon),
            'geoElements' => $this->generateUrl('geo_elements', $tripId + $latLon),
            'mapOption' => $this->generateUrl('trip_edit_map_option', $tripId),
            'liveShowStage' => $this->generateUrl('live_show_stage', $tripId + ['stage' => 0]),
        ]);
    }
}
