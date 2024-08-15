<?php

namespace App\Entity;

use App\Repository\StageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Cascade]
#[ORM\Entity(repositoryClass: StageRepository::class)]
class Stage implements MappableInterface
{
    use MappableTrait;

    #[ORM\Column]
    protected string $timezone = 'UTC';

    #[ORM\ManyToOne(inversedBy: 'stages')]
    #[ORM\JoinColumn(nullable: false)]
    protected Trip $trip;

    #[ORM\OneToOne(mappedBy: 'startStage')]
    protected ?Routing $routingOut = null;

    #[ORM\OneToOne(mappedBy: 'finishStage')]
    protected ?Routing $routingIn = null;

    protected ?string $symbol = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->arrivingAt = new \DateTimeImmutable('midnight');
    }

    /**
     * Override so we are sure to have midnight version.
     */
    public function getArrivingAt(): \DateTimeImmutable
    {
        return $this->arrivingAt->setTime(0, 0);
    }

    /**
     * Override so we are sure to have midnight version.
     */
    public function setArrivingAt(\DateTimeImmutable $arrivingAt): self
    {
        $this->arrivingAt = $arrivingAt->setTime(0, 0);

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getRoutingOut(): ?Routing
    {
        return $this->routingOut;
    }

    public function getRoutingIn(): ?Routing
    {
        return $this->routingIn;
    }

    public function setRoutingOut(?Routing $routingOut): self
    {
        $this->routingOut = $routingOut;

        return $this;
    }

    public function setRoutingIn(?Routing $routingIn): self
    {
        $this->routingIn = $routingIn;

        return $this;
    }
}
