<?php

namespace App\Message;

use App\MessageHandler\SplitSegmentsMessageHandler;

/**
 * @see SplitSegmentsMessageHandler
 */
readonly class SplitSegmentsMessage
{
    public function __construct(
        public int $tripId,
    ) {
    }
}
