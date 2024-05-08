<?php

namespace App\Entity;

use App\Model\Point;
use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    use UpdateNowTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?string $shareKey;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** @var Collection<int, Stage> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Stage::class, orphanRemoval: true)]
    private Collection $stages;

    /** @var Collection<int, Routing> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Routing::class, orphanRemoval: true)]
    private Collection $routings;

    #[ORM\Embedded]
    private GeoPoint $mapCenter;

    #[ORM\Column]
    private int $mapZoom;

    /** @var Collection<int, Tiles> */
    #[Assert\Count(min: 1, minMessage: 'You are required to have at least one set of Tiles.')]
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Tiles::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tiles;

    /** @var Collection<int, Interest> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Interest::class, orphanRemoval: true)]
    private Collection $interests;

    /** @var Collection<int, DiaryEntry> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: DiaryEntry::class, orphanRemoval: true)]
    private Collection $diaryEntries;

    /** @var Collection<int, Segment> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Segment::class, orphanRemoval: true)]
    private Collection $segments;

    /** @var ?array<mixed> */
    #[ORM\Column(nullable: true)]
    private ?array $progressPointStore = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->stages = new ArrayCollection();
        $this->routings = new ArrayCollection();
        $this->tiles = new ArrayCollection();
        $this->interests = new ArrayCollection();
        $this->diaryEntries = new ArrayCollection();
        $this->segments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getShareKey(): ?string
    {
        return $this->shareKey;
    }

    public function setShareKey(?string $shareKey): self
    {
        $this->shareKey = $shareKey;

        return $this;
    }

    public function startShare(): self
    {
        if (!$this->shareKey) {
            $this->shareKey = implode('-', mb_str_split(mb_substr(sha1(random_bytes(80)), 0, 12), 4));
        }

        return $this;
    }

    public function stopShare(): self
    {
        $this->shareKey = null;

        return $this;
    }

    public function isShared(): bool
    {
        return null !== $this->shareKey;
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
     * @return Collection<int, Stage>
     */
    public function getStages(): Collection
    {
        return $this->stages;
    }

    public function addStage(Stage $stage): self
    {
        if (!$this->stages->contains($stage)) {
            $this->stages->add($stage);
            $stage->setTrip($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Routing>
     */
    public function getRoutings(): Collection
    {
        return $this->routings;
    }

    public function addRouting(Routing $routing): self
    {
        if (!$this->routings->contains($routing)) {
            $this->routings->add($routing);
            $routing->setTrip($this);
        }

        return $this;
    }

    public function getMapCenter(): GeoPoint
    {
        return $this->mapCenter;
    }

    public function setMapCenter(GeoPoint $mapCenter): self
    {
        $this->mapCenter = $mapCenter;

        return $this;
    }

    public function getMapZoom(): int
    {
        return $this->mapZoom;
    }

    public function setMapZoom(int $mapZoom): self
    {
        $this->mapZoom = $mapZoom;

        return $this;
    }

    /**
     * @return Collection<int, Tiles>
     */
    public function getTiles(): Collection
    {
        return $this->tiles;
    }

    public function addTile(Tiles $tile): self
    {
        if (!$this->tiles->contains($tile)) {
            $this->tiles->add($tile);
            $tile->setTrip($this);
        }

        return $this;
    }

    public function removeTile(Tiles $tile): self
    {
        $this->tiles->removeElement($tile);

        return $this;
    }

    /**
     * @return Collection<int, Interest>
     */
    public function getInterests(): Collection
    {
        return $this->interests;
    }

    public function addInterest(Interest $interest): self
    {
        if (!$this->interests->contains($interest)) {
            $this->interests->add($interest);
        }

        return $this;
    }

    public function removeInterest(Interest $interest): self
    {
        $this->interests->removeElement($interest);

        return $this;
    }

    /**
     * @return Collection<int, DiaryEntry>
     */
    public function getDiaryEntries(): Collection
    {
        return $this->diaryEntries;
    }

    public function addDiaryEntry(DiaryEntry $diaryEntry): self
    {
        if (!$this->diaryEntries->contains($diaryEntry)) {
            $this->diaryEntries->add($diaryEntry);
        }

        return $this;
    }

    public function removeDiaryEntry(DiaryEntry $diaryEntry): self
    {
        $this->diaryEntries->removeElement($diaryEntry);

        return $this;
    }

    /**
     * @return Collection<int, Segment>
     */
    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(Segment $segment): self
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
        }

        return $this;
    }

    /**
     * @internal
     *
     * @return ?array<mixed>
     */
    public function getProgressPointStore(): ?array
    {
        return $this->progressPointStore;
    }

    /**
     * @param ?array<mixed> $progressPointStore
     */
    public function setProgressPointStore(?array $progressPointStore): static
    {
        $this->progressPointStore = $progressPointStore;

        return $this;
    }

    public function setProgressPoint(?Point $point): static
    {
        if ($point) {
            $this->progressPointStore = [$point->lat, $point->lon, $point->el];
        } else {
            $this->progressPointStore = null;
        }

        return $this;
    }

    public function getProgressPoint(): ?Point
    {
        if (empty($this->progressPointStore)) {
            return null;
        }

        return new Point($this->progressPointStore[0], $this->progressPointStore[1], $this->progressPointStore[2]);
    }
}
