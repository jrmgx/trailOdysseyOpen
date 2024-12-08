<?php

namespace App\Message;

use App\MessageHandler\UpdateElevationMessageHandler;

/**
 * @see UpdateElevationMessageHandler
 */
class UpdateElevationMessage
{
    public function __construct(
        public readonly int $tripId,
        public readonly int $updatedSegment = 0,
    ) {
    }
}
