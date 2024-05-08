<?php

namespace App\Entity;

/**
 * Bag or Gear.
 */
interface Things
{
    public function getWeight(): ?int;

    public function getName(): string;

    public function isInCurrentBag(): bool;

    public function isBag(): bool;
}
