<?php

namespace App\Entity;

use App\Constraint as AppAssert;
use App\Repository\StageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Cascade]
#[AppAssert\StageDateConstraint]
#[ORM\Entity(repositoryClass: StageRepository::class)]
class Stage implements MappableInterface
{
    use MappableTrait;

    #[ORM\Column]
    #[Assert\NotBlank]
    protected \DateTimeImmutable $leavingAt;

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

    protected bool $cascadeTimeChange = true;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCascadeTimeChange(): bool
    {
        return $this->cascadeTimeChange;
    }

    public function setCascadeTimeChange(bool $cascadeTimeChange): self
    {
        $this->cascadeTimeChange = $cascadeTimeChange;

        return $this;
    }

    public function getLeavingAt(): \DateTimeImmutable
    {
        return $this->leavingAt;
    }

    public function setLeavingAt(\DateTimeImmutable $leavingAt): self
    {
        $this->leavingAt = $leavingAt;

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
