<?php

namespace App\Entity;

trait UpdateNowTrait
{
    public function updatedNow(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
