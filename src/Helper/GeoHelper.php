<?php

namespace App\Helper;

use App\Model\Path;
use App\Model\Point;
use phpGPX\Models\Point as GpxPoint;

class GeoHelper
{
    private const K_RAD = \M_PI / 180;

    /**
     * @see https://github.com/diversen/gps-from-exif/blob/master/gps.php
     *
     * @param array<mixed> $exif
     */
    public static function getPointFromExif(array $exif): ?Point
    {
        if (!isset($exif['GPSLatitude']) || !isset($exif['GPSLongitude']) || !isset($exif['GPSLatitudeRef'])) {
            return null;
        }

        $latM = 1;
        $lonM = 1;
        if ('S' === $exif['GPSLatitudeRef']) {
            $latM = -1;
        }
        if ('W' === $exif['GPSLongitudeRef']) {
            $lonM = -1;
        }

        // Get the GPS data
        $gps['LatDegree'] = $exif['GPSLatitude'][0];
        $gps['LatMinute'] = $exif['GPSLatitude'][1];
        $gps['LatSeconds'] = $exif['GPSLatitude'][2];
        $gps['LongDegree'] = $exif['GPSLongitude'][0];
        $gps['LongMinute'] = $exif['GPSLongitude'][1];
        $gps['LongSeconds'] = $exif['GPSLongitude'][2];

        // Convert strings to numbers
        foreach ($gps as $key => $value) {
            if (false !== mb_strpos($value, '/')) {
                $temp = explode('/', $value);
                $gps[$key] = (float) $temp[0] / (float) $temp[1];
            }
        }

        // Calculate the decimal degree
        return new Point(
            (string) ($latM * ($gps['LatDegree'] + ($gps['LatMinute'] / 60) + ($gps['LatSeconds'] / 3600))),
            (string) ($lonM * ($gps['LongDegree'] + ($gps['LongMinute'] / 60) + ($gps['LongSeconds'] / 3600)))
        );
    }

    /**
     * @return array{minLat: string, maxLat: string, minLon: string, maxLon: string}
     */
    public static function getBoundingBox(Path $path): array
    {
        $minLat = $maxLat = (float) $path->getFirstPoint()->lat;
        $minLon = $maxLon = (float) $path->getFirstPoint()->lon;

        foreach ($path->getPoints() as $point) {
            if ((float) $point->lat < $minLat) {
                $minLat = (float) $point->lat;
            }
            if ((float) $point->lat > $maxLat) {
                $maxLat = (float) $point->lat;
            }
            if ((float) $point->lon < $minLon) {
                $minLon = (float) $point->lon;
            }
            if ((float) $point->lon > $maxLon) {
                $maxLon = (float) $point->lon;
            }
        }

        return [
            'minLat' => (string) $minLat,
            'maxLat' => (string) $maxLat,
            'minLon' => (string) $minLon,
            'maxLon' => (string) $maxLon,
        ];
    }

    /**
     * Inspiration from https://developer.mozilla.org/en-US/docs/Games/Techniques/2D_collision_detection#axis-aligned_bounding_box
     * The bounding box are considered squares.
     *
     * @param array{minLat: string, maxLat: string, minLon: string, maxLon: string} $bbox1
     * @param array{minLat: string, maxLat: string, minLon: string, maxLon: string} $bbox2
     * @param float                                                                 $tolerance Tolerance in percent (0 = 0%, 1 = 100%)
     */
    public static function doBoundingBoxesOverlap(array $bbox1, array $bbox2, float $tolerance = 0): bool
    {
        $minLon1 = (float) $bbox1['minLon'];
        $maxLon1 = (float) $bbox1['maxLon'];
        $minLat1 = (float) $bbox1['minLat'];
        $maxLat1 = (float) $bbox1['maxLat'];
        $heightLat1 = ($maxLat1 - $minLat1) * (1 + $tolerance);
        $widthLon1 = ($maxLon1 - $minLon1) * (1 + $tolerance);

        $minLon2 = (float) $bbox2['minLon'];
        $maxLon2 = (float) $bbox2['maxLon'];
        $minLat2 = (float) $bbox2['minLat'];
        $maxLat2 = (float) $bbox2['maxLat'];
        $heightLat2 = ($maxLat2 - $minLat2) * (1 + $tolerance);
        $widthLon2 = ($maxLon2 - $minLon2) * (1 + $tolerance);

        return
            $minLon1 < $minLon2 + $widthLon2
            && $minLon1 + $widthLon1 > $minLon2
            && $minLat1 < $minLat2 + $heightLat2
            && $heightLat1 + $minLat1 > $minLat2
        ;
    }

    public static function midPoint(Point $point1, Point $point2): Point
    {
        $lat1 = (float) $point1->lat;
        $lon1 = (float) $point1->lon;
        $lat2 = (float) $point2->lat;
        $lon2 = (float) $point2->lon;

        $dLon = deg2rad($lon2 - $lon1);

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $lon1 = deg2rad($lon1);

        $Bx = cos($lat2) * cos($dLon);
        $By = cos($lat2) * sin($dLon);
        $lat3 = atan2(sin($lat1) + sin($lat2), sqrt((cos($lat1) + $Bx) * (cos($lat1) + $Bx) + $By * $By));
        $lon3 = $lon1 + atan2($By, cos($lat1) + $Bx);

        return new Point((string) rad2deg($lat3), (string) rad2deg($lon3));
    }

    public static function metersToKilometers(int|string|null $meters, int $precision = 0): ?string
    {
        if (null === $meters) {
            return null;
        }

        $kilometers = ((int) $meters) / 1000;

        return number_format($kilometers, $precision);
    }

    /**
     * @return int Distance in meters
     */
    public static function calculateDistance(Point $point1, Point $point2, bool $withElevation = false): int
    {
        if ($withElevation) {
            return (int) \phpGPX\Helpers\GeoHelper::getRealDistance(
                self::getGpxPoint($point1),
                self::getGpxPoint($point2)
            );
        }

        return (int) \phpGPX\Helpers\GeoHelper::getRawDistance(
            self::getGpxPoint($point1),
            self::getGpxPoint($point2)
        );
    }

    /**
     * https://jamesloper.com/fastest-way-to-calculate-distance-between-two-coordinates.
     *
     * @return int Distance in meters
     */
    public static function calculateDistanceFast(Point $a, Point $b): int
    {
        $aLat = (float) $a->lat;
        $aLon = (float) $a->lon;
        $bLat = (float) $b->lat;
        $bLon = (float) $b->lon;

        $kx = cos($aLat * self::K_RAD) * 111.321;
        $dx = ($aLon - $bLon) * $kx;
        $dy = ($aLat - $bLat) * 111.139;

        return (int) (sqrt($dx ** 2 + $dy ** 2) * 1000);
    }

    /**
     * @param array<Point> $points
     */
    public static function calculateDistanceFromPoints(array $points, bool $withElevation = false): int
    {
        $distance = 0;

        for ($i = 0; $i < \count($points) - 1; ++$i) {
            $distance += self::calculateDistance($points[$i], $points[$i + 1], $withElevation);
        }

        return $distance;
    }

    /**
     * @param array<Point> $points
     */
    public static function findPointAtDistance(array $points, int $distance): ?Point
    {
        $distanceAccumulation = 0;

        for ($i = 0; $i < \count($points) - 1; ++$i) {
            $distanceAccumulation += self::calculateDistance($points[$i], $points[$i + 1]);
            if ($distanceAccumulation >= $distance) {
                return $points[$i];
            }
        }

        return null;
        // throw new \Exception('Did not find a Point at that distance.');
    }

    /**
     * @return array{lat: float, lon: float}
     */
    public static function xyzToLatLon(float $x, float $y, float $z): array
    {
        $n = 2 ** $z;
        $lon_deg = $x / $n * 360.0 - 180.0;
        $lat_rad = atan(sinh(\M_PI * (1 - 2 * $y / $n)));
        $lat_deg = $lat_rad * 180.0 / \M_PI;

        return ['lat' => $lat_deg, 'lon' => $lon_deg];
    }

    /**
     * @return array{north: float, west: float, south: float, east: float}
     */
    public static function xyzToBoundingBox(float $x, float $y, float $z): array
    {
        $topLeft = self::xyzToLatLon($x, $y, $z);
        $bottomRight = self::xyzToLatLon($x + 1, $y + 1, $z);

        return [
            'north' => $topLeft['lat'],
            'west' => $topLeft['lon'],
            'south' => $bottomRight['lat'],
            'east' => $bottomRight['lon'],
        ];
    }

    private static function getGpxPoint(Point $point): GpxPoint
    {
        $gpxPoint = new GpxPoint('');
        $gpxPoint->latitude = (float) $point->lat;
        $gpxPoint->longitude = (float) $point->lon;
        $gpxPoint->elevation = $point->el ? (float) $point->el : null;

        return $gpxPoint;
    }

    /**
     * Some path are not useful for us, like roundabout.
     * A roundabout is detected when the extremities of the "path" are very close
     * and the length of the path is longer than the distance between the extremities.
     *
     * @param array<int, Point> $points
     */
    public static function isRoundabout(array $points, int $delta = 20, int $maxLength = 100): bool
    {
        $length = self::calculateDistanceFromPoints($points);
        if ($length > $maxLength) {
            // A path that is > to $maxLength will be kept
            return false;
        }
        $distance = self::calculateDistance($points[0], $points[\count($points) - 1]);
        if ($distance > $delta) {
            return false;
        }

        return $distance < $length;
    }
}
