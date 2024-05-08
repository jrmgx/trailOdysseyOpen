<?php

namespace App\Entity;

use App\Repository\BagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BagRepository::class)]
class Bag implements Things, InBag
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(nullable: true)]
    private ?int $weight = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Trip $trip;

    /** @var Collection<int, GearInBag> */
    #[ORM\OneToMany(mappedBy: 'bag', targetEntity: GearInBag::class, orphanRemoval: true)]
    private Collection $gearsInBag;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'bagsInBag')]
    private ?self $parentBag = null;

    /** @var Collection<int, Bag> */
    #[ORM\OneToMany(mappedBy: 'parentBag', targetEntity: self::class)]
    private Collection $bagsInBag;

    #[ORM\Column]
    private bool $checked = false;

    /**
     * Calculated value for ThingsBagType form.
     */
    private bool $isInCurrentBag = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->gearsInBag = new ArrayCollection();
        $this->bagsInBag = new ArrayCollection();
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

    public function getTrip(): Trip
    {
        return $this->trip;
    }

    public function setTrip(Trip $trip): self
    {
        $this->trip = $trip;

        return $this;
    }

    /**
     * @return Collection<int, GearInBag>
     */
    public function getGearsInBag(): Collection
    {
        return $this->gearsInBag;
    }

    public function getTotalWeight(): int
    {
        $weight = $this->getWeight() ?? 0;
        foreach ($this->gearsInBag as $gearInBag) {
            $weight += $gearInBag->getWeight() ?? 0;
        }

        foreach ($this->bagsInBag as $bag) {
            $weight += $bag->getTotalWeight();
        }

        return $weight;
    }

    public function getTotalCheckedWeight(): int
    {
        $weight = $this->getCheckedWeight();
        foreach ($this->gearsInBag as $gearInBag) {
            $weight += $gearInBag->getCheckedWeight();
        }

        foreach ($this->bagsInBag as $bag) {
            if ($bag->getChecked()) {
                $weight += $bag->getTotalCheckedWeight();
            }
        }

        return $weight;
    }

    public function getSomethingIsChecked(): bool
    {
        foreach ($this->gearsInBag as $gearInBag) {
            if ($gearInBag->getChecked()) {
                return true;
            }
        }

        foreach ($this->bagsInBag as $bag) {
            if ($bag->getChecked()) {
                return true;
            }
        }

        return false;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function getCheckedWeight(): int
    {
        if (!$this->isInCurrentBag()) {
            return $this->getWeight() ?? 0;
        }

        if ($this->getChecked()) {
            return $this->getWeight() ?? 0;
        }

        return 0;
    }

    public function setWeight(?int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * @return array<int, InBag>
     */
    public function inBag(): array
    {
        $inBag = array_merge($this->gearsInBag->toArray(), $this->bagsInBag->toArray());
        usort($inBag, function (InBag $a, InBag $b) {
            if ($a->getChecked() === $b->getChecked()) {
                // Adding an emoji will push the entry to the end
                return ($a->getThing()->isBag() ? '🛄 ' : '') . $a->getThing()->getName()
                    <=> ($b->getThing()->isBag() ? '🛄 ' : '') . $b->getThing()->getName();
            }

            return !$a->getChecked() <=> !$b->getChecked();
        });

        return $inBag;
    }

    public function getParentBag(): ?self
    {
        return $this->parentBag;
    }

    public function canAddBagInBag(self $childCandidate): bool
    {
        return !$this->createsLoop($childCandidate);
    }

    private function createsLoop(self $bag): bool
    {
        $parentBag = $this;
        while (null !== $parentBag) {
            if ($parentBag === $bag) {
                return true;
            }
            $parentBag = $parentBag->getParentBag();
        }

        return false;
    }

    public function setParentBag(?self $parentBag): static
    {
        $this->parentBag = $parentBag;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getBagsInBag(): Collection
    {
        return $this->bagsInBag;
    }

    public function addBagsInBag(self $bagsInBag): static
    {
        if (!$this->canAddBagInBag($bagsInBag)) {
            return $this;
        }

        if (!$this->bagsInBag->contains($bagsInBag)) {
            $this->bagsInBag->add($bagsInBag);
            $bagsInBag->setParentBag($this);
        }

        return $this;
    }

    public function removeBagsInBag(self $bagsInBag): static
    {
        if ($this->bagsInBag->removeElement($bagsInBag)) {
            // set the owning side to null (unless already changed)
            if ($bagsInBag->getParentBag() === $this) {
                $bagsInBag->setParentBag(null);
            }
        }

        return $this;
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

    public function getChecked(): bool
    {
        return $this->checked;
    }

    public function getThing(): Things
    {
        return $this;
    }

    public function getCount(): int
    {
        return 1;
    }

    public function setChecked(bool $checked): self
    {
        $this->checked = $checked;

        return $this;
    }

    public function isBag(): bool
    {
        return true;
    }
}
