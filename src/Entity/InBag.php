<?php

namespace App\Entity;

interface InBag
{
    public function getChecked(): bool;

    public function getThing(): Things;

    public function getCount(): int;

    public function getWeight(): ?int;

    public function getCheckedWeight(): int;
}
