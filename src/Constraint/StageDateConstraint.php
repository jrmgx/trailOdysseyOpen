<?php

namespace App\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @see https://symfony.com/doc/current/validation/custom_constraint.html#class-constraint-validator
 */
#[\Attribute]
class StageDateConstraint extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
