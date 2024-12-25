<?php

namespace App\Entity;

use App\Model\Extra;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait MappableTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    protected string $name = '%POINT_NAME%';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $description = null;

    #[ORM\Column]
    protected \DateTimeImmutable $updatedAt;

    #[ORM\Column]
    #[Assert\NotBlank]
    protected \DateTimeImmutable $arrivingAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    protected User $user;

    #[ORM\Column]
    protected string $pointName = '';

    #[ORM\Embedded]
    #[Assert\NotBlank]
    protected GeoPoint $point;

    protected ?Extra $extra = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): MappableInterface
    {
        $this->name = $name;

        return $this;
    }

    public function getNameWithPointName(): string
    {
        return trim((string) str_replace('%POINT_NAME%', $this->getPointName(), $this->getName()));
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): MappableInterface
    {
        $this->description = $description;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): MappableInterface
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getArrivingAt(): \DateTimeImmutable
    {
        return $this->arrivingAt;
    }

    public function setArrivingAt(\DateTimeImmutable $arrivingAt): self
    {
        $this->arrivingAt = $arrivingAt;

        return $this;
    }

    public function getTrip(): Trip
    {
        return $this->trip;
    }

    public function setTrip(Trip $trip): MappableInterface
    {
        $this->trip = $trip;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): MappableInterface
    {
        $this->user = $user;

        return $this;
    }

    public function getPoint(): GeoPoint
    {
        return $this->point;
    }

    public function setPoint(GeoPoint $point): void
    {
        $this->point = $point;
    }

    public function getExtra(): ?Extra
    {
        return $this->extra;
    }

    public function setExtra(?Extra $extra): self
    {
        $this->extra = $extra;

        return $this;
    }

    public function getSymbol(): string
    {
        return $this->symbol ?? '❔';
    }

    public function setSymbol(?string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function hasSymbol(): bool
    {
        return null !== $this->symbol;
    }

    public function getPointName(): string
    {
        return $this->pointName;
    }

    public function setPointName(string $pointName): self
    {
        $this->pointName = $pointName;

        return $this;
    }
}
