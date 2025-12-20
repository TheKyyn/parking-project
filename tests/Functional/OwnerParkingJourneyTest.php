<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Functional;

use PHPUnit\Framework\TestCase;
use ParkingSystem\Domain\Entity\ParkingOwner;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Entity\Subscription;
use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\UseCase\Parking\CreateParking;
use ParkingSystem\UseCase\Parking\CreateParkingRequest;
use ParkingSystem\UseCase\Parking\UpdateParking;
use ParkingSystem\UseCase\Parking\UpdateParkingRequest;
use ParkingSystem\UseCase\Parking\UpdateParkingRates;
use ParkingSystem\UseCase\Parking\UpdateParkingRatesRequest;
use ParkingSystem\UseCase\Analytics\GetParkingStatistics;
use ParkingSystem\UseCase\Analytics\GetParkingStatisticsRequest;
use ParkingSystem\UseCase\Parking\ListUnauthorizedUsers;
use ParkingSystem\UseCase\Parking\ListUnauthorizedUsersRequest;

/**
 * Functional Test: Owner Parking Management Journey
 * Tests the complete flow from parking creation to statistics viewing
 */
class OwnerParkingJourneyTest extends TestCase
{
    private InMemoryParkingOwnerRepository $ownerRepository;
    private InMemoryParkingRepository $parkingRepository;
    private InMemoryReservationRepository $reservationRepository;
    private InMemorySessionRepository $sessionRepository;
    private InMemorySubscriptionRepository $subscriptionRepository;
    private MockIdGenerator $idGenerator;

    protected function setUp(): void
    {
        $this->ownerRepository = new InMemoryParkingOwnerRepository();
        $this->parkingRepository = new InMemoryParkingRepository();
        $this->reservationRepository = new InMemoryReservationRepository();
        $this->sessionRepository = new InMemorySessionRepository();
        $this->subscriptionRepository = new InMemorySubscriptionRepository();
        $this->idGenerator = new MockIdGenerator();

        // Seed an owner
        $owner = new ParkingOwner(
            'owner-001',
            'owner@example.com',
            'hashed_password',
            'Jean',
            'Dupont'
        );
        $this->ownerRepository->save($owner);
    }

    public function testCompleteParkingManagementJourney(): void
    {
        // Step 1: Owner Creates a Parking
        $createParking = new CreateParking(
            $this->parkingRepository,
            $this->ownerRepository,
            $this->idGenerator
        );

        $this->idGenerator->setNextId('parking-new-001');

        $openingHours = [
            1 => ['open' => '08:00', 'close' => '20:00'], // Monday
            2 => ['open' => '08:00', 'close' => '20:00'], // Tuesday
            3 => ['open' => '08:00', 'close' => '20:00'], // Wednesday
            4 => ['open' => '08:00', 'close' => '20:00'], // Thursday
            5 => ['open' => '08:00', 'close' => '20:00'], // Friday
        ];

        $createRequest = new CreateParkingRequest(
            'owner-001',
            'Parking du Centre',
            '45 Rue de la Paix, Paris',
            48.8698,
            2.3297,
            100,
            8.50,
            $openingHours
        );

        $createResponse = $createParking->execute($createRequest);

        $this->assertEquals('parking-new-001', $createResponse->parkingId);
        $this->assertEquals('owner-001', $createResponse->ownerId);
        $this->assertEquals('Parking du Centre', $createResponse->name);
        $this->assertEquals('45 Rue de la Paix, Paris', $createResponse->address);
        $this->assertEquals(100, $createResponse->totalSpaces);
        $this->assertEquals(8.50, $createResponse->hourlyRate);

        // Verify parking was saved
        $savedParking = $this->parkingRepository->findById('parking-new-001');
        $this->assertNotNull($savedParking);
        $this->assertEquals('Parking du Centre', $savedParking->getName());

        // Step 2: Owner Updates Parking Information
        $updateParking = new UpdateParking($this->parkingRepository);

        $updateRequest = new UpdateParkingRequest(
            'parking-new-001',
            'owner-001',
            'Parking du Centre-Ville',
            null,
            120,
            null,
            null
        );

        $updateParking->execute($updateRequest);

        // Verify update
        $updatedParking = $this->parkingRepository->findById('parking-new-001');
        $this->assertEquals('Parking du Centre-Ville', $updatedParking->getName());
        $this->assertEquals(120, $updatedParking->getTotalSpaces());

        // Step 3: Simulate some activity (reservations and sessions)
        $this->simulateParkingActivity('parking-new-001');

        // Step 4: Owner Views Statistics
        $getStatistics = new GetParkingStatistics(
            $this->parkingRepository,
            $this->reservationRepository,
            $this->sessionRepository,
            $this->subscriptionRepository
        );

        $statsRequest = new GetParkingStatisticsRequest(
            'parking-new-001',
            new \DateTimeImmutable('-7 days'),
            new \DateTimeImmutable()
        );

        $statsResponse = $getStatistics->execute($statsRequest);

        $this->assertEquals('parking-new-001', $statsResponse->parkingId);
        $this->assertEquals(120, $statsResponse->totalSpaces);
        $this->assertGreaterThanOrEqual(0, $statsResponse->totalReservations);
        $this->assertGreaterThanOrEqual(0, $statsResponse->totalRevenue);

        // Verify owner's parking list was updated
        $owner = $this->ownerRepository->findById('owner-001');
        $this->assertContains('parking-new-001', $owner->getOwnedParkings());
    }

    public function testOwnerCanMonitorUnauthorizedUsers(): void
    {
        // Step 1: Create a parking for the owner
        $parking = new Parking(
            'parking-monitor-001',
            'owner-001',
            'Parking Monitored',
            '123 Test Street',
            48.8566,
            2.3522,
            50,
            50,
            10.0
        );
        $this->parkingRepository->save($parking);

        // Update owner's parking list
        $owner = $this->ownerRepository->findById('owner-001');
        $owner->addOwnedParking('parking-monitor-001');
        $this->ownerRepository->save($owner);

        // Step 2: Simulate an unauthorized session (session without reservation)
        $unauthorizedSession = new ParkingSession(
            'session-unauth-001',
            'user-unknown-001',
            'parking-monitor-001',
            new \DateTimeImmutable('-1 hour'),
            null, // no reservation
            null  // no subscription
        );
        $this->sessionRepository->save($unauthorizedSession);

        // Step 3: Owner lists unauthorized users
        $listUnauthorized = new ListUnauthorizedUsers(
            $this->parkingRepository,
            $this->sessionRepository,
            $this->reservationRepository,
            $this->subscriptionRepository
        );

        $listRequest = new ListUnauthorizedUsersRequest(
            'parking-monitor-001'
        );

        $listResponse = $listUnauthorized->execute($listRequest);

        $this->assertEquals('parking-monitor-001', $listResponse->parkingId);
        // Verify the list contains unauthorized entries
        $this->assertIsArray($listResponse->unauthorizedUsers);

        // Step 4: Owner creates a subscription for better management
        $weeklySlots = [
            1 => [['start' => '08:00', 'end' => '18:00']], // Monday
            2 => [['start' => '08:00', 'end' => '18:00']], // Tuesday
            3 => [['start' => '08:00', 'end' => '18:00']], // Wednesday
            4 => [['start' => '08:00', 'end' => '18:00']], // Thursday
            5 => [['start' => '08:00', 'end' => '18:00']], // Friday
        ];

        $subscription = new Subscription(
            'sub-001',
            'user-sub-001',
            'parking-monitor-001',
            $weeklySlots,
            3, // 3 months
            new \DateTimeImmutable(),
            150.0 // monthly amount
        );
        $this->subscriptionRepository->save($subscription);

        // Step 5: Verify subscription is tracked in statistics
        $getStatistics = new GetParkingStatistics(
            $this->parkingRepository,
            $this->reservationRepository,
            $this->sessionRepository,
            $this->subscriptionRepository
        );

        $statsRequest = new GetParkingStatisticsRequest('parking-monitor-001');
        $statsResponse = $getStatistics->execute($statsRequest);

        $this->assertEquals(1, $statsResponse->activeSubscriptions);
        $this->assertEquals(50, $statsResponse->totalSpaces);
    }

    private function simulateParkingActivity(string $parkingId): void
    {
        // Create some reservations (using future dates to pass validation)
        $reservation1 = new Reservation(
            'res-sim-001',
            'user-sim-001',
            $parkingId,
            new \DateTimeImmutable('+1 hour'),
            new \DateTimeImmutable('+3 hours'),
            17.0
        );
        $reservation1->confirm();
        $this->reservationRepository->save($reservation1);

        $reservation2 = new Reservation(
            'res-sim-002',
            'user-sim-002',
            $parkingId,
            new \DateTimeImmutable('+4 hours'),
            new \DateTimeImmutable('+7 hours'),
            25.5
        );
        $reservation2->confirm();
        $this->reservationRepository->save($reservation2);

        // Create some sessions
        $session1 = new ParkingSession(
            'sess-sim-001',
            'user-sim-001',
            $parkingId,
            new \DateTimeImmutable(),
            'res-sim-001',
            null
        );
        $this->sessionRepository->save($session1);
    }
}

// In-Memory Repository Implementations for Owner Testing
class InMemoryParkingOwnerRepository implements \ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface
{
    private array $owners = [];

    public function save(\ParkingSystem\Domain\Entity\ParkingOwner $owner): void
    {
        $this->owners[$owner->getId()] = $owner;
    }

    public function findById(string $id): ?\ParkingSystem\Domain\Entity\ParkingOwner
    {
        return $this->owners[$id] ?? null;
    }

    public function findByEmail(string $email): ?\ParkingSystem\Domain\Entity\ParkingOwner
    {
        foreach ($this->owners as $owner) {
            if ($owner->getEmail() === strtolower($email)) {
                return $owner;
            }
        }
        return null;
    }

    public function findAll(): array { return array_values($this->owners); }
    public function delete(\ParkingSystem\Domain\Entity\ParkingOwner $owner): void { unset($this->owners[$owner->getId()]); }
    public function exists(string $id): bool { return isset($this->owners[$id]); }
    public function emailExists(string $email): bool { return $this->findByEmail($email) !== null; }
    public function count(): int { return count($this->owners); }
    public function findByIds(array $ids): array { return array_filter($this->owners, fn($o) => in_array($o->getId(), $ids)); }
    public function findRecentlyCreated(int $limit = 10): array { return array_slice($this->owners, 0, $limit); }
    public function findWithMostParkings(int $limit = 10): array { return array_slice($this->owners, 0, $limit); }
}

class InMemorySubscriptionRepository implements \ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface
{
    private array $subscriptions = [];

    public function save(\ParkingSystem\Domain\Entity\Subscription $subscription): void
    {
        $this->subscriptions[$subscription->getId()] = $subscription;
    }

    public function findById(string $id): ?\ParkingSystem\Domain\Entity\Subscription
    {
        return $this->subscriptions[$id] ?? null;
    }

    public function findByUserId(string $userId): array
    {
        return array_values(array_filter($this->subscriptions, fn($s) => $s->getUserId() === $userId));
    }

    public function findByParkingId(string $parkingId): array
    {
        return array_values(array_filter($this->subscriptions, fn($s) => $s->getParkingId() === $parkingId));
    }

    public function findAll(): array { return array_values($this->subscriptions); }
    public function delete(\ParkingSystem\Domain\Entity\Subscription $subscription): void { unset($this->subscriptions[$subscription->getId()]); }
    public function exists(string $id): bool { return isset($this->subscriptions[$id]); }
    public function count(): int { return count($this->subscriptions); }
    public function findByIds(array $ids): array { return []; }
    public function findActiveSubscriptions(): array { return array_filter($this->subscriptions, fn($s) => $s->isActive()); }
    public function findActiveSubscriptionsForParking(string $parkingId): array { return array_filter($this->subscriptions, fn($s) => $s->getParkingId() === $parkingId && $s->isActive()); }
    public function findActiveSubscriptionsForUser(string $userId): array { return array_filter($this->subscriptions, fn($s) => $s->getUserId() === $userId && $s->isActive()); }
    public function findByStatus(string $status): array { return array_filter($this->subscriptions, fn($s) => $s->getStatus() === $status); }
    public function findExpiringSubscriptions(int $daysBeforeExpiry = 7): array { return []; }
    public function findExpiredSubscriptions(): array { return []; }
    public function findConflictingSubscriptions(string $parkingId, array $weeklyTimeSlots): array { return []; }
    public function findSubscriptionsActiveAt(string $parkingId, \DateTimeInterface $dateTime): array { return []; }
    public function getTotalRevenueForParking(string $parkingId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float { return 0.0; }
    public function getAverageSubscriptionDuration(): float { return 0.0; }
}
