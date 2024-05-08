<?php

namespace App\Model;

class SearchElementResult
{
    /**
     * @param array<string, string> $details
     */
    private function __construct(
        public Point $point,
        public string $name,
        public array $details,
        public bool $error = false,
    ) {
    }

    public static function fromError(Point $point, string $message): self
    {
        return new self($point, $message, [], true);
    }

    /**
     * @param array<mixed> $element
     */
    public static function fromElementResult(array $element, string $sourceKey, string $sourceValue): ?self
    {
        if (!isset($element['type']) || ('node' !== $element['type'] && 'way' !== $element['type'])) {
            return null;
        }
        if (!isset($element['tags'])) {
            return null;
        }

        $details = [];
        $tags = $element['tags'];
        foreach ($tags as $key => $value) {
            if (str_contains($key, 'name') || str_contains($key, $sourceKey)) {
                continue; // drop
            }
            $details[(string) str_replace([':', '_'], ' ', $key)] = $value;
        }

        // node.lat vs way.center.lat
        $lat = $element['lat'] ?? $element['center']['lat'];
        $lon = $element['lon'] ?? $element['center']['lon'];

        return new self(
            new Point($lat, $lon),
            $element['tags']['name'] ?? ucfirst(str_replace('_', ' ', $sourceValue)),
            $details,
        );
    }
}
