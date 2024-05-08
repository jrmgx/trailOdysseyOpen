<?php

namespace App\Tests\Service;

use App\Helper\GeoHelper;
use App\Model\Path;
use App\Model\Point;
use PHPUnit\Framework\TestCase;

class GeoHelperTest extends TestCase
{
    /**
     * @return array<array{Path, string, string, string, string, bool}>
     */
    public static function dataPaths(): array
    {
        $pathA = self::pathFromJson('[{"lat":"47.69081468588481","lon":"0.23350311279297","el":null},{"lat":"47.759637380334595","lon":"0.13526916503906253","el":null}]');
        $pathB = self::pathFromJson('[{"lat":"47.74255566748737","lon":"0.19569396972656253","el":null},{"lat":"47.71807749987609","lon":"0.15586853027343753","el":null}]');
        $pathC = self::pathFromJson('[{"lat":"47.55428670127958","lon":"-0.24925231933593753","el":null},{"lat":"47.586715439092934","lon":"-0.16960144042968753","el":null}]');
        $pathD = self::pathFromJson('[{"lat":"47.53899190311993","lon":"-0.22865295410156253","el":null},{"lat":"47.604774168947614","lon":"-0.24993896484375003","el":null}]');
        $pathE = self::pathFromJson('[{"lat":"47.73562905149295","lon":"-0.05424499511718751","el":null},{"lat":"47.70514099299205","lon":"-0.0968170166015625","el":null},{"lat":"47.745787772920956","lon":"-0.15518188476562503","el":null},{"lat":"47.7619452898863","lon":"-0.12840270996093753","el":null}]');
        $pathF = self::pathFromJson('[{"lat":"47.762406859510584","lon":"-0.06454467773437501","el":null},{"lat":"47.78870955868773","lon":"-0.092010498046875","el":null},{"lat":"47.76517619125417","lon":"-0.15792846679687503","el":null},{"lat":"47.70144425833172","lon":"-0.17852783203125003","el":null}]');

        return [
            [ // 0
                new Path([
                    new Point('47.593198777144636', '1.338958740234375'),
                    new Point('48.007381433478855', '0.19638061523437503'),
                ]),
                (string) (float) '47.593198777144636',
                (string) (float) '48.007381433478855',
                (string) (float) '0.19638061523437503',
                (string) (float) '1.338958740234375',
                true, // Yes it crosses the next bounding box
            ], [ // 1
                new Path([
                    new Point('47.908978314728714', '1.9033813476562502'),
                    new Point('47.79286140021344', '1.0574340820312502'),
                ]),
                (string) (float) '47.79286140021344',
                (string) (float) '47.908978314728714',
                (string) (float) '1.0574340820312502',
                (string) (float) '1.9033813476562502',
                false, // Does not cross the next bonding box one
            ], [ // 2
                new Path([
                    new Point('47.38905261221537', '0.6811523437500001'),
                    new Point('47.47451936570433', '-0.5465698242187501'),
                ]),
                (string) (float) '47.38905261221537',
                (string) (float) '47.47451936570433',
                (string) (float) '-0.5465698242187501',
                (string) (float) '0.6811523437500001',
                true, // This one contains the next one (so it crosses it in our logic)
            ], [ // 3
                new Path([
                    new Point('47.44759373848233', '-0.45593261718750006'),
                    new Point('47.41089699288201', '0.5438232421875001'),
                ]),
                (string) (float) '47.41089699288201',
                (string) (float) '47.44759373848233',
                (string) (float) '-0.45593261718750006',
                (string) (float) '0.5438232421875001',
                false, // Does not cross A bonding box
            ], [ // 4
                $pathA,
                '47.690814685885',
                '47.759637380335',
                '0.13526916503906',
                '0.23350311279297',
                true, // Cross B bonding box
            ], [ // 5
                $pathB,
                '47.718077499876',
                '47.742555667487',
                '0.15586853027344',
                '0.19569396972656',
                false, // Does not cross C bonding box,
            ], [ // 6
                $pathC,
                '47.55428670128',
                '47.586715439093',
                '-0.24925231933594',
                '-0.16960144042969',
                true, // Cross D bonding box,
            ], [ // 7
                $pathD,
                '47.53899190312',
                '47.604774168948',
                '-0.24993896484375',
                '-0.22865295410156',
                false, // Does not cross E bonding box,
            ], [ // 8
                $pathE,
                '47.705140992992',
                '47.761945289886',
                '-0.15518188476563',
                '-0.054244995117188',
                true, // Cross F bonding box,
            ], [ // 9
                $pathF,
                '47.701444258332',
                '47.788709558688',
                '-0.17852783203125',
                '-0.064544677734375',
                false, // Not defined,
            ],
        ];
    }

    /**
     * Create bounding boxes for dataPaths taking it one with the next.
     *
     * @return array<array{
     *     array{minLat: string, maxLat: string, minLon: string, maxLon: string},
     *     array{minLat: string, maxLat: string, minLon: string, maxLon: string},
     *     bool
     * }>
     */
    public static function dataBondingBoxes(): iterable
    {
        $paths = self::dataPaths();
        for ($i = 0; $i < \count($paths) - 1; ++$i) {
            $path0 = $paths[$i][0];
            $path1 = $paths[$i + 1][0];
            yield [
                GeoHelper::getBoundingBox($path0),
                GeoHelper::getBoundingBox($path1),
                $paths[$i][5], // Do it cross the next bounding box
            ];
        }
    }

    /**
     * @dataProvider dataPaths
     */
    public function testGetBoundingBox(Path $path, string $minLat, string $maxLat, string $minLon, string $maxLon): void
    {
        self::assertSame([
            'minLat' => $minLat,
            'maxLat' => $maxLat,
            'minLon' => $minLon,
            'maxLon' => $maxLon,
        ], GeoHelper::getBoundingBox($path));
    }

    /**
     * @param array{minLat: string, maxLat: string, minLon: string, maxLon: string} $bb1
     * @param array{minLat: string, maxLat: string, minLon: string, maxLon: string} $bb2
     *
     * @dataProvider dataBondingBoxes
     */
    public function testDoBoundingBoxesOverlap(array $bb1, array $bb2, bool $cross): void
    {
        self::assertSame($cross, GeoHelper::doBoundingBoxesOverlap($bb1, $bb2));
    }

    private static function pathFromJson(string $json): Path
    {
        $points = [];
        foreach (json_decode($json, true) as $point) {
            $points[] = new Point($point['lat'], $point['lon'], $point['el'] ?? null);
        }

        return new Path($points);
    }
}
