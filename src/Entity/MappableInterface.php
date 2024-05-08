<?php

namespace App\Entity;

use App\Model\Extra;

interface MappableInterface
{
    public const PHOTO_TYPE = 'photo';

    public function getId(): ?int;

    public function getName(): string;

    public function setName(string $name): self;

    public function getPointName(): string;

    public function setPointName(string $pointName): self;

    public function getNameWithPointName(): string;

    public function getDescription(): ?string;

    public function setDescription(?string $description): self;

    public function getUpdatedAt(): \DateTimeImmutable;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self;

    public function getArrivingAt(): \DateTimeImmutable;

    public function setArrivingAt(\DateTimeImmutable $arrivingAt): self;

    public function getTrip(): Trip;

    public function setTrip(Trip $trip): self;

    public function getUser(): User;

    public function setUser(User $user): self;

    public function getPoint(): GeoPoint;

    public function setPoint(GeoPoint $point): void;

    public function getExtra(): ?Extra;

    public function setExtra(?Extra $extra): self;

    public function getSymbol(): ?string;

    public function setSymbol(?string $number): self;
}
