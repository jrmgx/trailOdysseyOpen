<?php

namespace App\Service;

use App\Entity\Stage;

class StageService
{
    public static function isValidRegardingPrev(Stage $stage, ?Stage $prev, string &$error = ''): bool
    {
        $prevStageLeavingAt = $prev?->getLeavingAt();
        if ($prevStageLeavingAt && $stage->getArrivingAt() <= $prevStageLeavingAt) {
            $error = 'The arriving time should be after the previous Stage: Later than ' .
                    $prevStageLeavingAt->format('d/m/Y H:i')
            ;

            return false;
        }

        return true;
    }

    public static function isValidRegardingNext(Stage $stage, ?Stage $next, string &$error = ''): bool
    {
        $nextStageArrivingAt = $next?->getArrivingAt();
        if ($nextStageArrivingAt && $stage->getLeavingAt() >= $nextStageArrivingAt) {
            $error = 'The leaving time should be before the previous Stage: Earlier than ' .
                $nextStageArrivingAt->format('d/m/Y H:i')
            ;

            return false;
        }

        return true;
    }
}
