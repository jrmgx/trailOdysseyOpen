<?php

namespace App\Entity;

use App\Repository\GearRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GearRepository::class)]
class Gear implements Things
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $weight = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** @var Collection<int, GearInBag> */
    #[ORM\OneToMany(targetEntity: GearInBag::class, mappedBy: 'gear', orphanRemoval: true)]
    private Collection $gearsInBag;

    /**
     * Calculated value for ThingsBagType form.
     */
    private bool $isInCurrentBag = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->gearsInBag = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, GearInBag>
     */
    public function getGearsInBag(): Collection
    {
        return $this->gearsInBag;
    }

    public function isInCurrentBag(): bool
    {
        return $this->isInCurrentBag;
    }

    public function setIsInCurrentBag(bool $isInCurrentBag): self
    {
        $this->isInCurrentBag = $isInCurrentBag;

        return $this;
    }

    public function isBag(): bool
    {
        return false;
    }
}
