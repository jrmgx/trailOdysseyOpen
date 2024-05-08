<?php

namespace App\Twig;

use App\Helper\GeoHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class GeoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('to_km', GeoHelper::metersToKilometers(...)),
        ];
    }
}
