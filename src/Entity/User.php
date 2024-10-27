<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['username'], message: 'form.error.email_exist')]
#[UniqueEntity(fields: ['nickname'], message: 'form.error.nickname_exist')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $username;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 80)]
    #[Assert\Regex('`^[a-z0-9_\.-]+$`i')]
    private string $nickname;

    /** @var array<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    /** @var Collection<int, Trip> */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $trips;

    #[ORM\Column(length: 32, options: ['default' => 'UTC'])]
    private string $timezone = 'UTC';

    #[ORM\Column(length: 64, options: ['default' => '{counter}{stage_name}{trip_name}'])]
    #[Assert\Regex('`^(\{(counter|stage_name|trip_name)\}){3}$`', 'The pattern must only contain {counter}, {stage_name} and {trip_name} in any order')]
    private string $exportFilenamePattern = '{counter}{stage_name}{trip_name}';

    /**
     * Set of mastodon info to connect to the related mastodon app.
     *
     * @var ?array{accessToken: string, instanceUrl: string}
     */
    #[ORM\Column(nullable: true)]
    private ?array $mastodonInfo = null;

    public function __construct()
    {
        $this->trips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setNickname(string $nickname): self
    {
        $this->nickname = mb_strtolower($nickname);

        return $this;
    }

    public function setEmail(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_unique($roles);

        return $this;
    }

    public function addRole(string $string): self
    {
        $roles = $this->getRoles();
        $roles[] = $string;

        return $this->setRoles($roles);
    }

    public function removeRole(string $string): self
    {
        return $this->setRoles(array_filter($this->getRoles(), fn (string $role) => $role !== $string));
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): self
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setUser($this);
        }

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getExportFilenamePattern(): string
    {
        return $this->exportFilenamePattern;
    }

    public function setExportFilenamePattern(string $exportFilenamePattern): static
    {
        $this->exportFilenamePattern = $exportFilenamePattern;

        return $this;
    }

    /**
     * @return ?array{accessToken: string, instanceUrl: string}
     */
    public function getMastodonInfo(): ?array
    {
        return $this->mastodonInfo;
    }

    /**
     * @param ?array{accessToken: string, instanceUrl: string} $mastodonInfo
     */
    public function setMastodonInfo(?array $mastodonInfo): static
    {
        $this->mastodonInfo = $mastodonInfo;

        return $this;
    }

    public function isConnectedToMastodon(): bool
    {
        return !empty($this->mastodonInfo);
    }
}
