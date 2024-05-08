<?php

namespace App\Entity;

use App\Repository\GearInBagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GearInBagRepository::class)]
class GearInBag implements InBag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'gearsInBag')]
    #[ORM\JoinColumn(nullable: false)]
    private Gear $gear;

    #[ORM\ManyToOne(inversedBy: 'gearsInBag')]
    #[ORM\JoinColumn(nullable: false)]
    private Bag $bag;

    #[ORM\Column]
    private int $count = 1;

    #[ORM\Column]
    private bool $checked = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getGear(): Gear
    {
        return $this->gear;
    }

    public function setGear(Gear $gear): self
    {
        $this->gear = $gear;

        return $this;
    }

    public function getBag(): Bag
    {
        return $this->bag;
    }

    public function setBag(Bag $bag): self
    {
        $this->bag = $bag;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getWeight(): ?int
    {
        if (null === $this->gear->getWeight()) {
            return null;
        }

        return $this->gear->getWeight() * $this->count;
    }

    public function getCheckedWeight(): int
    {
        if ($this->getChecked()) {
            return $this->getWeight() ?? 0;
        }

        return 0;
    }

    public function getChecked(): bool
    {
        return $this->checked;
    }

    public function setChecked(bool $checked): self
    {
        $this->checked = $checked;

        return $this;
    }

    public function getUser(): User
    {
        return $this->bag->getUser();
    }

    public function getThing(): Things
    {
        return $this->gear;
    }
}
