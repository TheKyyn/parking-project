<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Functional;

use PHPUnit\Framework\TestCase;
use ParkingSystem\Domain\Entity\User;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\UseCase\User\CreateUser;
use ParkingSystem\UseCase\User\CreateUserRequest;
use ParkingSystem\UseCase\User\AuthenticateUser;
use ParkingSystem\UseCase\User\AuthenticateUserRequest;
use ParkingSystem\UseCase\Reservation\CreateReservation;
use ParkingSystem\UseCase\Reservation\CreateReservationRequest;
use ParkingSystem\UseCase\Session\EnterParking;
use ParkingSystem\UseCase\Session\EnterParkingRequest;
use ParkingSystem\UseCase\Session\ExitParking;
use ParkingSystem\UseCase\Session\ExitParkingRequest;

/**
 * Functional Test: User Reservation Journey
 * Tests the complete flow from user registration to parking exit
 */
class UserReservationJourneyTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryParkingRepository $parkingRepository;
    private InMemoryReservationRepository $reservationRepository;
    private InMemorySessionRepository $sessionRepository;
    private MockPasswordHasher $passwordHasher;
    private MockIdGenerator $idGenerator;
    private MockJwtGenerator $jwtGenerator;
    private MockPricingCalculator $pricingCalculator;
    private MockConflictChecker $conflictChecker;
    private MockEntryValidator $entryValidator;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->parkingRepository = new InMemoryParkingRepository();
        $this->reservationRepository = new InMemoryReservationRepository();
        $this->sessionRepository = new InMemorySessionRepository();
        $this->passwordHasher = new MockPasswordHasher();
        $this->idGenerator = new MockIdGenerator();
        $this->jwtGenerator = new MockJwtGenerator();
        $this->pricingCalculator = new MockPricingCalculator();
        $this->conflictChecker = new MockConflictChecker();
        $this->entryValidator = new MockEntryValidator();

        // Seed a parking
        $parking = new Parking(
            'parking-001',
            'owner-001',
            'Central Parking',
            '123 Main Street, City',
            48.8566,
            2.3522,
            50,
            50,
            10.0
        );
        $this->parkingRepository->save($parking);
    }

    public function testCompleteUserReservationJourney(): void
    {
        // Step 1: User Registration
        $createUser = new CreateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->idGenerator
        );

        $this->idGenerator->setNextId('user-test-001');
        $registerRequest = new CreateUserRequest(
            'john.doe@example.com',
            'SecurePassword123',
            'John',
            'Doe'
        );

        $registerResponse = $createUser->execute($registerRequest);

        $this->assertEquals('user-test-001', $registerResponse->userId);
        $this->assertEquals('john.doe@example.com', $registerResponse->email);
        $this->assertStringContainsString('John', $registerResponse->fullName);
        $this->assertStringContainsString('Doe', $registerResponse->fullName);

        // Step 2: User Authentication
        $authenticateUser = new AuthenticateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtGenerator
        );

        $loginRequest = new AuthenticateUserRequest(
            'john.doe@example.com',
            'SecurePassword123'
        );

        $loginResponse = $authenticateUser->execute($loginRequest);

        $this->assertNotEmpty($loginResponse->token);
        $this->assertEquals('user-test-001', $loginResponse->userId);

        // Step 3: Create Reservation
        $this->conflictChecker->setHasAvailableSpaces(true);
        $this->pricingCalculator->setPrice(20.0); // 2 hours at 10€/hour
        $this->idGenerator->setNextId('reservation-001');

        $createReservation = new CreateReservation(
            $this->reservationRepository,
            $this->parkingRepository,
            $this->userRepository,
            $this->conflictChecker,
            $this->pricingCalculator,
            $this->idGenerator
        );

        $startTime = new \DateTimeImmutable('+1 hour');
        $endTime = new \DateTimeImmutable('+3 hours');

        $reservationRequest = new CreateReservationRequest(
            'user-test-001',
            'parking-001',
            $startTime,
            $endTime
        );

        $reservationResponse = $createReservation->execute($reservationRequest);

        $this->assertEquals('reservation-001', $reservationResponse->reservationId);
        $this->assertEquals('user-test-001', $reservationResponse->userId);
        $this->assertEquals('parking-001', $reservationResponse->parkingId);
        $this->assertEquals(20.0, $reservationResponse->totalAmount);
        $this->assertEquals('confirmed', $reservationResponse->status);

        // Step 4: Enter Parking
        $this->entryValidator->setHasValidAuthorization(true);
        $this->entryValidator->setReservationId('reservation-001');
        $this->idGenerator->setNextId('session-001');

        $enterParking = new EnterParking(
            $this->sessionRepository,
            $this->parkingRepository,
            $this->userRepository,
            $this->entryValidator,
            $this->idGenerator
        );

        $enterRequest = new EnterParkingRequest(
            'user-test-001',
            'parking-001'
        );

        $enterResponse = $enterParking->execute($enterRequest);

        $this->assertEquals('session-001', $enterResponse->sessionId);
        $this->assertEquals('user-test-001', $enterResponse->userId);
        $this->assertEquals('parking-001', $enterResponse->parkingId);
        $this->assertEquals('active', $enterResponse->status);

        // Step 5: Exit Parking
        $exitParking = new ExitParking(
            $this->sessionRepository,
            $this->parkingRepository,
            $this->reservationRepository,
            new MockSessionPricingCalculator(),
            $this->entryValidator
        );

        $exitRequest = new ExitParkingRequest('session-001');
        $exitResponse = $exitParking->execute($exitRequest);

        $this->assertEquals('session-001', $exitResponse->sessionId);
        $this->assertEquals('completed', $exitResponse->status);
        $this->assertGreaterThan(0, $exitResponse->totalAmount);

        // Verify final state
        $session = $this->sessionRepository->findById('session-001');
        $this->assertNotNull($session);
        $this->assertTrue($session->isCompleted());
    }

    public function testUserCanViewReservationHistory(): void
    {
        // Create user
        $user = new User(
            'user-002',
            'jane@example.com',
            $this->passwordHasher->hash('password'),
            'Jane',
            'Smith'
        );
        $this->userRepository->save($user);

        // Create multiple reservations
        $startTime = new \DateTimeImmutable('+1 hour');
        $endTime = new \DateTimeImmutable('+2 hours');

        $reservation1 = new Reservation(
            'res-001',
            'user-002',
            'parking-001',
            $startTime,
            $endTime,
            15.0
        );
        $reservation1->confirm();
        $this->reservationRepository->save($reservation1);

        $startTime2 = new \DateTimeImmutable('+3 hours');
        $endTime2 = new \DateTimeImmutable('+4 hours');

        $reservation2 = new Reservation(
            'res-002',
            'user-002',
            'parking-001',
            $startTime2,
            $endTime2,
            15.0
        );
        $reservation2->confirm();
        $this->reservationRepository->save($reservation2);

        // Verify user can see their reservations
        $userReservations = $this->reservationRepository->findByUserId('user-002');

        $this->assertCount(2, $userReservations);
        $this->assertEquals('user-002', $userReservations[0]->getUserId());
        $this->assertEquals('user-002', $userReservations[1]->getUserId());
    }
}

// In-Memory Repository Implementations for Testing
class InMemoryUserRepository implements \ParkingSystem\Domain\Repository\UserRepositoryInterface
{
    private array $users = [];

    public function save(\ParkingSystem\Domain\Entity\User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    public function findById(string $id): ?\ParkingSystem\Domain\Entity\User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?\ParkingSystem\Domain\Entity\User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === strtolower($email)) {
                return $user;
            }
        }
        return null;
    }

    public function findAll(): array { return array_values($this->users); }
    public function delete(\ParkingSystem\Domain\Entity\User $user): void { unset($this->users[$user->getId()]); }
    public function exists(string $id): bool { return isset($this->users[$id]); }
    public function count(): int { return count($this->users); }
    public function findByIds(array $ids): array { return array_filter($this->users, fn($u) => in_array($u->getId(), $ids)); }
    public function emailExists(string $email): bool { return $this->findByEmail($email) !== null; }
    public function findRecentlyCreated(int $limit = 10): array { return array_slice(array_values($this->users), 0, $limit); }
}

class InMemoryParkingRepository implements \ParkingSystem\Domain\Repository\ParkingRepositoryInterface
{
    private array $parkings = [];

    public function save(\ParkingSystem\Domain\Entity\Parking $parking): void
    {
        $this->parkings[$parking->getId()] = $parking;
    }

    public function findById(string $id): ?\ParkingSystem\Domain\Entity\Parking
    {
        return $this->parkings[$id] ?? null;
    }

    public function findAll(): array { return array_values($this->parkings); }
    public function findByOwnerId(string $ownerId): array { return array_filter($this->parkings, fn($p) => $p->getOwnerId() === $ownerId); }
    public function delete(\ParkingSystem\Domain\Entity\Parking $parking): void { unset($this->parkings[$parking->getId()]); }
    public function exists(string $id): bool { return isset($this->parkings[$id]); }
    public function count(): int { return count($this->parkings); }
    public function findByIds(array $ids): array { return []; }
    public function findNearLocation(\ParkingSystem\Domain\ValueObject\GpsCoordinates $location, float $radiusInKilometers, int $limit = 10): array { return []; }
    public function findAvailableAt(\DateTimeInterface $dateTime, int $limit = 10): array { return []; }
    public function findByMinimumSpaces(int $minimumSpaces): array { return []; }
    public function findByRateRange(float $minRate, float $maxRate): array { return []; }
    public function findMostPopular(int $limit = 10): array { return []; }
    public function searchByCriteria(array $criteria): array { return []; }
    public function updateAvailableSpots(string $parkingId, int $availableSpots): void {}
}

class InMemoryReservationRepository implements \ParkingSystem\Domain\Repository\ReservationRepositoryInterface
{
    private array $reservations = [];

    public function save(\ParkingSystem\Domain\Entity\Reservation $reservation): void
    {
        $this->reservations[$reservation->getId()] = $reservation;
    }

    public function findById(string $id): ?\ParkingSystem\Domain\Entity\Reservation
    {
        return $this->reservations[$id] ?? null;
    }

    public function findByUserId(string $userId): array
    {
        return array_values(array_filter($this->reservations, fn($r) => $r->getUserId() === $userId));
    }

    public function findByParkingId(string $parkingId): array
    {
        return array_values(array_filter($this->reservations, fn($r) => $r->getParkingId() === $parkingId));
    }

    public function findAll(): array { return array_values($this->reservations); }
    public function delete(\ParkingSystem\Domain\Entity\Reservation $reservation): void { unset($this->reservations[$reservation->getId()]); }
    public function exists(string $id): bool { return isset($this->reservations[$id]); }
    public function count(): int { return count($this->reservations); }
    public function findByIds(array $ids): array { return []; }
    public function findActiveReservations(): array { return array_filter($this->reservations, fn($r) => $r->getStatus() === 'confirmed'); }
    public function findActiveReservationsForParking(string $parkingId): array { return array_filter($this->reservations, fn($r) => $r->getParkingId() === $parkingId && $r->getStatus() === 'confirmed'); }
    public function findActiveReservationsForUser(string $userId): array { return array_filter($this->reservations, fn($r) => $r->getUserId() === $userId && $r->getStatus() === 'confirmed'); }
    public function findReservationsInTimeRange(\DateTimeInterface $startTime, \DateTimeInterface $endTime): array { return []; }
    public function findConflictingReservations(string $parkingId, \DateTimeInterface $startTime, \DateTimeInterface $endTime): array { return []; }
    public function findByStatus(string $status): array { return array_filter($this->reservations, fn($r) => $r->getStatus() === $status); }
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array { return []; }
    public function findExpiredReservations(): array { return []; }
    public function getTotalRevenueForParking(string $parkingId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float { return 0.0; }
    public function findByParkingIds(array $parkingIds): array { return array_values(array_filter($this->reservations, fn($r) => in_array($r->getParkingId(), $parkingIds))); }
}

class InMemorySessionRepository implements \ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface
{
    private array $sessions = [];

    public function save(\ParkingSystem\Domain\Entity\ParkingSession $session): void
    {
        $this->sessions[$session->getId()] = $session;
    }

    public function findById(string $id): ?\ParkingSystem\Domain\Entity\ParkingSession
    {
        return $this->sessions[$id] ?? null;
    }

    public function findByUserId(string $userId): array
    {
        return array_values(array_filter($this->sessions, fn($s) => $s->getUserId() === $userId));
    }

    public function findByParkingId(string $parkingId): array
    {
        return array_values(array_filter($this->sessions, fn($s) => $s->getParkingId() === $parkingId));
    }

    public function findAll(): array { return array_values($this->sessions); }
    public function delete(\ParkingSystem\Domain\Entity\ParkingSession $session): void { unset($this->sessions[$session->getId()]); }
    public function exists(string $id): bool { return isset($this->sessions[$id]); }
    public function count(): int { return count($this->sessions); }
    public function findByIds(array $ids): array { return []; }
    public function findActiveSessions(): array { return array_filter($this->sessions, fn($s) => $s->isActive()); }
    public function findActiveSessionsForParking(string $parkingId): array { return []; }
    public function findActiveSessionsForUser(string $userId): array { return []; }
    public function findActiveSessionByUserAndParking(string $userId, string $parkingId): ?\ParkingSystem\Domain\Entity\ParkingSession { return null; }
    public function findByStatus(string $status): array { return []; }
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array { return []; }
    public function findOverstayedSessions(): array { return []; }
    public function findSessionsWithoutReservation(): array { return []; }
    public function findSessionsByReservationId(string $reservationId): array { return []; }
    public function getTotalRevenueForParking(string $parkingId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float { return 0.0; }
    public function getAverageSessionDuration(string $parkingId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float { return 0.0; }
}

// Mock Services
class MockPasswordHasher implements \ParkingSystem\UseCase\User\PasswordHasherInterface
{
    public function hash(string $password): string { return 'hashed_' . $password; }
    public function verify(string $password, string $hash): bool { return $hash === 'hashed_' . $password; }
}

class MockIdGenerator implements \ParkingSystem\UseCase\User\IdGeneratorInterface, \ParkingSystem\UseCase\Reservation\IdGeneratorInterface, \ParkingSystem\UseCase\Session\IdGeneratorInterface
{
    private string $nextId = 'test-id';
    public function setNextId(string $id): void { $this->nextId = $id; }
    public function generate(): string { return $this->nextId; }
}

class MockJwtGenerator implements \ParkingSystem\UseCase\User\JwtTokenGeneratorInterface
{
    public function generate(array $payload, int $expirationSeconds): string { return 'jwt_token_' . ($payload['user_id'] ?? 'unknown'); }
    public function verify(string $token): array { return ['user_id' => str_replace('jwt_token_', '', $token)]; }
    public function decode(string $token): array { return ['user_id' => str_replace('jwt_token_', '', $token)]; }
}

class MockPricingCalculator implements \ParkingSystem\UseCase\Reservation\PricingCalculatorInterface
{
    private float $price = 10.0;
    public function setPrice(float $price): void { $this->price = $price; }
    public function calculateReservationPrice(float $hourlyRate, \DateTimeInterface $startTime, \DateTimeInterface $endTime): float { return $this->price; }
    public function calculateWithProgressiveRates(string $parkingId, \DateTimeInterface $startTime, \DateTimeInterface $endTime): float { return $this->price; }
}

class MockConflictChecker implements \ParkingSystem\UseCase\Reservation\ConflictCheckerInterface
{
    private bool $hasSpaces = true;
    public function setHasAvailableSpaces(bool $value): void { $this->hasSpaces = $value; }
    public function hasConflicts(string $parkingId, \DateTimeInterface $startTime, \DateTimeInterface $endTime, ?string $excludeReservationId = null): bool { return !$this->hasSpaces; }
    public function getAvailableSpacesAt(string $parkingId, \DateTimeInterface $dateTime): int { return $this->hasSpaces ? 10 : 0; }
    public function hasAvailableSpacesDuring(string $parkingId, \DateTimeInterface $startTime, \DateTimeInterface $endTime, int $requiredSpaces = 1): bool { return $this->hasSpaces; }
}

class MockEntryValidator implements \ParkingSystem\UseCase\Session\EntryValidatorInterface
{
    private bool $hasAuth = true;
    private ?string $reservationId = null;
    public function setHasValidAuthorization(bool $value): void { $this->hasAuth = $value; }
    public function setReservationId(?string $id): void { $this->reservationId = $id; }
    public function hasActiveReservation(string $userId, string $parkingId, \DateTimeInterface $dateTime): bool { return $this->reservationId !== null; }
    public function hasActiveSubscription(string $userId, string $parkingId, \DateTimeInterface $dateTime): bool { return false; }
    public function getActiveReservationId(string $userId, string $parkingId, \DateTimeInterface $dateTime): ?string { return $this->reservationId; }
    public function getAuthorizedEndTime(string $userId, string $parkingId, \DateTimeInterface $dateTime): ?\DateTimeInterface { return new \DateTimeImmutable('+2 hours'); }
}

class MockSessionPricingCalculator implements \ParkingSystem\UseCase\Session\PricingCalculatorInterface
{
    public function calculateSessionPrice(float $hourlyRate, \DateTimeInterface $startTime, \DateTimeInterface $endTime): float { return 15.0; }
    public function calculateOverstayPenalty(float $hourlyRate, \DateTimeInterface $authorizedEnd, \DateTimeInterface $actualEnd): float { return 0.0; }
}
