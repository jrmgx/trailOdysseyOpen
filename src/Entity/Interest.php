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

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
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
}
