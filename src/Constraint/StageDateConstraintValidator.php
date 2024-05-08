<?php

namespace App\Constraint;

use App\Entity\Stage;
use App\Service\StageService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class StageDateConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $stage, Constraint $constraint): void
    {
        if (!$stage instanceof Stage) {
            throw new UnexpectedValueException($stage, Stage::class);
        }

        if (!$constraint instanceof StageDateConstraint) {
            throw new UnexpectedValueException($constraint, StageDateConstraint::class);
        }

        // The stage is asked to cascade time changes on next ones, so they will be calculated
        if ($stage->getCascadeTimeChange()) {
            return;
        }

        if ($stage->getArrivingAt() > $stage->getLeavingAt()) {
            $this->context
                ->buildViolation('The arriving time must be before (or equal to) the leaving time.')
                ->atPath('arrivingAt')
                ->addViolation()
            ;

            return;
        }

        $error = '';
        if (!StageService::isValidRegardingPrev($stage, $stage->getRoutingIn()?->getStartStage(), $error)) {
            $this->context
                ->buildViolation($error)
                ->atPath('arrivingAt')
                ->addViolation()
            ;

            return;
        }

        if (!StageService::isValidRegardingNext($stage, $stage->getRoutingOut()?->getFinishStage(), $error)) {
            $this->context
                ->buildViolation($error)
                ->atPath('leavingAt')
                ->addViolation()
            ;
            // return;
        }
    }
}
