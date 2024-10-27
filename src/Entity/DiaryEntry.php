<?php

namespace App\Entity;

use App\Repository\DiaryEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Cascade]
#[ORM\Entity(repositoryClass: DiaryEntryRepository::class)]
class DiaryEntry implements MappableInterface
{
    use MappableTrait;

    #[ORM\ManyToOne(inversedBy: 'diaryEntries')]
    #[ORM\JoinColumn(nullable: false)]
    protected Trip $trip;

    #[ORM\Column(length: 16, nullable: true)]
    protected ?string $type = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Assert\Length(max: 16)]
    protected ?string $symbol = null;

    /**
     * This is an array that contains all the identifier where the diary has already been sent
     * So we can update the message if broadcast is asked again.
     *
     * @var ?array<string, string>
     */
    #[ORM\Column(nullable: true)]
    private ?array $broadcastIdentifiers = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSymbol(): string
    {
        if (!$this->symbol && self::PHOTO_TYPE === $this->type) {
            return '🏞️';
        }

        return $this->symbol ?? '💬';
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

    /**
     * @return array<string, string>
     */
    public function getBroadcastIdentifiers(): array
    {
        return $this->broadcastIdentifiers ?? [];
    }

    /**
     * @param array<string, string> $broadcastIdentifiers
     */
    public function setBroadcastIdentifiers(array $broadcastIdentifiers): static
    {
        $this->broadcastIdentifiers = $broadcastIdentifiers;

        return $this;
    }

    public function addBroadcastIdentifier(string $provider, string $broadcastIdentifier): static
    {
        if (!$this->broadcastIdentifiers) {
            $this->broadcastIdentifiers = [];
        }
        $this->broadcastIdentifiers[$provider] = $broadcastIdentifier;

        return $this;
    }
}
