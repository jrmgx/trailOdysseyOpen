<?php

namespace App\Message;

class UpdateElevationMessage
{
    public function __construct(
        public readonly int $tripId,
        public readonly int $updatedSegment = 0,
    ) {
    }
}
