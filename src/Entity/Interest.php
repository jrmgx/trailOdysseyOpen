<?php

namespace App\Entity;

use App\Repository\InterestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Cascade]
#[ORM\Entity(repositoryClass: InterestRepository::class)]
class Interest implements MappableInterface
{
    use MappableTrait;

    #[ORM\ManyToOne(inversedBy: 'interests')]
    #[ORM\JoinColumn(nullable: false)]
    protected Trip $trip;

    #[ORM\Column(length: 16, nullable: true)]
    protected ?string $type = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Assert\Length(max: 16)]
    protected ?string $symbol = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $checkpoint = false;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->arrivingAt = new \DateTimeImmutable('midnight');
    }

    /**
     * Same as {@see Stage}: date-only, time always midnight.
     */
    public function getArrivingAt(): \DateTimeImmutable
    {
        return $this->arrivingAt->setTime(0, 0);
    }

    public function setArrivingAt(\DateTimeImmutable $arrivingAt): self
    {
        $this->arrivingAt = $arrivingAt->setTime(0, 0);

        return $this;
    }

    public function getSymbol(): string
    {
        if (!$this->symbol && self::PHOTO_TYPE === $this->type) {
            return '🏞️';
        }

        return $this->symbol ?? 'ℹ️';
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isCheckpoint(): bool
    {
        return $this->checkpoint;
    }

    public function setCheckpoint(bool $checkpoint): self
    {
        $this->checkpoint = $checkpoint;

        return $this;
    }
}
