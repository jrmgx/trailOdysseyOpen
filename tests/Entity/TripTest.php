<?php

namespace App\Tests\Entity;

use App\Entity\Stage;
use App\Entity\Trip;
use PHPUnit\Framework\TestCase;

class TripTest extends TestCase
{
    private Trip $trip;

    protected function setUp(): void
    {
        $this->trip = new Trip();
    }

    public function testGetFirstStageReturnsNullWhenNoStages(): void
    {
        $this->assertNull($this->trip->getFirstStage());
    }

    public function testGetLastStageReturnsNullWhenNoStages(): void
    {
        $this->assertNull($this->trip->getLastStage());
    }

    public function testGetFirstStageReturnsEarliestStage(): void
    {
        // Create stages with different arrival times
        $stage1 = $this->createStage(new \DateTimeImmutable('2025-01-15'));
        $stage2 = $this->createStage(new \DateTimeImmutable('2025-01-10')); // Earliest
        $stage3 = $this->createStage(new \DateTimeImmutable('2025-01-20'));

        $this->trip->addStage($stage1);
        $this->trip->addStage($stage2);
        $this->trip->addStage($stage3);

        $this->assertSame($stage2, $this->trip->getFirstStage());
    }

    public function testGetLastStageReturnsLatestStage(): void
    {
        // Create stages with different arrival times
        $stage1 = $this->createStage(new \DateTimeImmutable('2025-01-15'));
        $stage2 = $this->createStage(new \DateTimeImmutable('2025-01-20')); // Latest
        $stage3 = $this->createStage(new \DateTimeImmutable('2025-01-10'));

        $this->trip->addStage($stage1);
        $this->trip->addStage($stage2);
        $this->trip->addStage($stage3);

        $this->assertSame($stage2, $this->trip->getLastStage());
    }

    public function testGetFirstStageWithSameArrivalTimeUsesLowestId(): void
    {
        $arrivalTime = new \DateTimeImmutable('2024-02-14');

        $stage1 = $this->createStage($arrivalTime);
        $stage2 = $this->createStage($arrivalTime);

        // Simulate IDs
        $this->setPrivateProperty($stage1, 'id', 2);
        $this->setPrivateProperty($stage2, 'id', 1);

        $this->trip->addStage($stage1);
        $this->trip->addStage($stage2);

        $this->assertSame($stage2, $this->trip->getFirstStage());
    }

    public function testGetLastStageWithSameArrivalTimeUsesHighestId(): void
    {
        $arrivalTime = new \DateTimeImmutable('2024-02-14');

        $stage1 = $this->createStage($arrivalTime);
        $stage2 = $this->createStage($arrivalTime);

        // Simulate IDs
        $this->setPrivateProperty($stage1, 'id', 2);
        $this->setPrivateProperty($stage2, 'id', 1);

        $this->trip->addStage($stage1);
        $this->trip->addStage($stage2);

        $this->assertSame($stage1, $this->trip->getLastStage());
    }

    private function createStage(\DateTimeImmutable $arrivingAt): Stage
    {
        $stage = new Stage();
        $stage->setArrivingAt($arrivingAt);

        return $stage;
    }

    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setValue($object, $value);
    }
}
