<?php

namespace App\Service;

use App\Entity\GeoPoint;
use App\Entity\Interest;
use App\Entity\MappableInterface;
use App\Entity\Segment;
use App\Entity\Trip;
use App\Helper\GeoHelper;
use App\Model\Point;
use App\Repository\InterestRepository;
use Doctrine\ORM\EntityManagerInterface;
use phpGPX\Models\GpxFile;
use phpGPX\Models\Link;
use phpGPX\Models\Metadata;
use phpGPX\Models\Person;
use phpGPX\Models\Point as GpxPoint;
use phpGPX\Models\Segment as GpxSegment;
use phpGPX\Models\Track;
use phpGPX\phpGPX;
use Symfony\Component\String\Slugger\AsciiSlugger;

readonly class GpxService
{
    public function __construct(
        private TripService $tripService,
        private InterestRepository $interestRepository,
        private EntityManagerInterface $entityManager,
        private string $projectName,
        private string $projectHost,
    ) {
    }

    public function gpxFile(string $filePath): GpxFile
    {
        $gpx = new phpGPX();

        return $gpx->load($filePath);
    }

    /**
     * This is used into a messenger message (async).
     */
    public function gpxFileToSegments(GpxFile $gpxFile, Trip $trip): void
    {
        $gpxName = $gpxFile->metadata?->name ?? '';
        if ('' !== $gpxName) {
            $gpxName = "$gpxName: ";
        }

        foreach ($gpxFile->routes as $route) {
            $points = [];
            foreach ($route->points as $point) {
                $points[] = new Point((string) $point->latitude, (string) $point->longitude, (string) $point->elevation);
            }

            if (\count($points) > 1 && !GeoHelper::isRoundabout($points)) {
                $segment = new Segment();
                $segment->setPoints($points);
                $segment->setName($gpxName . ($route->name ?? 'Unnamed'));
                $segment->setTrip($trip);
                $segment->setUser($trip->getUser());
                $this->entityManager->persist($segment);
                $this->entityManager->flush();
                $this->entityManager->detach($segment);
                unset($segment);
            }

            unset($points);
        }

        foreach ($gpxFile->tracks as $track) {
            foreach ($track->segments as $segment) {
                $points = [];
                foreach ($segment->points as $point) {
                    $points[] = new Point((string) $point->latitude, (string) $point->longitude, (string) $point->elevation);
                }

                if (\count($points) > 1 && !GeoHelper::isRoundabout($points)) {
                    $segment = new Segment();
                    $segment->setPoints($points);
                    $segment->setName($gpxName . ($track->name ?? 'Unnamed'));
                    $segment->setTrip($trip);
                    $segment->setUser($trip->getUser());
                    $this->entityManager->persist($segment);
                    $this->entityManager->flush();
                    $this->entityManager->detach($segment);
                    unset($segment);
                }

                unset($points);
            }
        }
    }

    /**
     * This is used into a messenger message (async).
     */
    public function gpxFileToInterests(GpxFile $gpxFile, Trip $trip): void
    {
        $gpxName = $gpxFile->metadata?->name;
        $date = (new \DateTimeImmutable())->modify('+ 1 days')->setTime(18, 0);
        foreach ($gpxFile->waypoints as $waypoint) {
            $point = new GeoPoint();
            $point->setLat((string) $waypoint->latitude);
            $point->setLon((string) $waypoint->longitude);

            $interest = new Interest();
            $interest->setName($waypoint->name ?? $gpxName ?? 'No Name');
            $interest->setArrivingAt($date);
            $interest->setPoint($point);
            $interest->setDescription(($waypoint->description ?? '') . \PHP_EOL . ($waypoint->comment ?? ''));
            $interest->setTrip($trip);
            $interest->setUser($trip->getUser());

            $this->entityManager->persist($interest);
        }

        $this->entityManager->flush();
    }

    /**
     * @return array<GpxFile>
     */
    public function buildGpx(Trip $trip): array
    {
        [, $stages, $routings] = $this->tripService->calculateResults($trip);

        $files = [];
        $global = $this->gpx($trip);

        $slug = new AsciiSlugger();
        $tripName = mb_substr(mb_strtolower($slug->slug($trip->getName())), 0, 16);

        $count = 0;
        foreach ($routings as $routing) {
            ++$count;

            $startStage = $routing->getStartStage();
            $finishStage = $routing->getFinishStage();

            $startGpxPoint = $this->point($startStage->getPoint()->toPoint());
            $finishGpxPoint = $this->point($finishStage->getPoint()->toPoint());

            $local = $this->gpx(
                $trip,
                'From ' . $startStage->getNameWithPointName() .
                ' to ' . $finishStage->getNameWithPointName()
            );

            // Add track
            if ($routing->getPathPoints()) {
                foreach ($routing->getPathPoints() as $point) {
                    $gpxPoint = $this->point($point);
                    $local->tracks[0]->segments[0]->points[] = $gpxPoint;
                    $global->tracks[0]->segments[0]->points[] = $gpxPoint;
                }
            } else {
                $local->tracks[0]->segments[0]->points[] = $startGpxPoint;
                $global->tracks[0]->segments[0]->points[] = $startGpxPoint;

                $local->tracks[0]->segments[0]->points[] = $finishGpxPoint;
                $global->tracks[0]->segments[0]->points[] = $finishGpxPoint;
            }

            // Add waypoint
            $startWayPoint = $this->waypoint($startStage);
            $local->waypoints[] = $startWayPoint;

            $finishWayPoint = $this->waypoint($finishStage);
            $local->waypoints[] = $finishWayPoint;

            $slugName = $slug->slug(
                $startStage->getNameWithPointName() .
                ' to ' . $finishStage->getNameWithPointName()
            );
            $filenamePattern = $trip->getUser()->getExportFilenamePattern();
            $filenamePattern = str_replace('}{', '}_{', $filenamePattern);
            $filename = str_replace([
                '{counter}', '{stage_name}', '{trip_name}',
            ], [
                str_pad((string) $count, 3, '0', \STR_PAD_LEFT),
                mb_substr(mb_strtolower($slugName), 0, 32),
                $tripName,
            ], $filenamePattern);

            $files[$filename] = $local;
        }

        foreach ($stages as $stage) {
            $global->waypoints[] = $this->waypoint($stage);
        }

        foreach ($this->interestRepository->findByTrip($trip) as $interest) {
            $global->waypoints[] = $this->waypoint($interest);
        }

        $files[$tripName] = $global;

        return $files;
    }

    private function gpx(Trip $trip, ?string $name = null): GpxFile
    {
        $name = $name ? ': ' . $name : '';

        $person = new Person();
        $person->name = $this->projectName . ' u#' . $trip->getUser()->getId();

        $gpx = new GpxFile();
        $gpx->creator = $this->projectName;
        $gpx->metadata = new Metadata();
        $gpx->metadata->name = $trip->getName() . $name;
        $gpx->metadata->description = $trip->getDescription();
        $gpx->metadata->author = $person;
        $gpx->metadata->time = new \DateTime();

        if ($trip->getShareKey()) {
            $link = new Link();
            $link->text = 'Public URL'; // TODO translate
            $link->href = $this->projectHost;

            $gpx->metadata->links[] = $link;
        }

        $track = new Track();
        $track->segments[] = new GpxSegment();

        $gpx->tracks[] = $track;

        return $gpx;
    }

    private static function point(Point $point): GpxPoint
    {
        $gpxPoint = new GpxPoint(GpxPoint::TRACKPOINT);
        $gpxPoint->latitude = (float) $point->lat;
        $gpxPoint->longitude = (float) $point->lon;
        $gpxPoint->elevation = (float) $point->el;

        return $gpxPoint;
    }

    private static function waypoint(MappableInterface $mappable): GpxPoint
    {
        $point = $mappable->getPoint()->toPoint();

        $gpxPoint = new GpxPoint(GpxPoint::WAYPOINT);
        $gpxPoint->latitude = (float) $point->lat;
        $gpxPoint->longitude = (float) $point->lon;
        $gpxPoint->elevation = (float) $point->el;
        $gpxPoint->time = \DateTime::createFromImmutable($mappable->getArrivingAt());
        $gpxPoint->name = $mappable->getNameWithPointName();
        if ($mappable->getSymbol()) {
            $gpxPoint->name = $mappable->getSymbol() . ' ' . $gpxPoint->name;
        }
        // TODO translate + date format
        $gpxPoint->description = 'Date: ' . $mappable->getArrivingAt()->format('D j M');
        if ($mappable->getDescription()) {
            $gpxPoint->description .= \PHP_EOL . $mappable->getDescription();
        }

        return $gpxPoint;
    }
}
