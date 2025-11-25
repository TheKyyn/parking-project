<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\User\CreateUser;
use ParkingSystem\UseCase\User\CreateUserRequest;
use ParkingSystem\UseCase\User\CreateUserResponse;
use ParkingSystem\UseCase\User\UserAlreadyExistsException;
use ParkingSystem\UseCase\User\PasswordHasherInterface;
use ParkingSystem\UseCase\User\IdGeneratorInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;
use ParkingSystem\Domain\Entity\User;

/**
 * CreateUserTest
 * Unit tests for CreateUser use case - Testing business logic with mocked dependencies
 */
class CreateUserTest extends TestCase
{
    private CreateUser $createUser;
    private MockObject|UserRepositoryInterface $userRepository;
    private MockObject|PasswordHasherInterface $passwordHasher;
    private MockObject|IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);

        $this->createUser = new CreateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->idGenerator
        );
    }

    public function testExecuteCreatesUserSuccessfully(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'john.doe@example.com',
            'password123',
            'John',
            'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('john.doe@example.com')
            ->willReturn(false);

        $this->idGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('user-123');

        $this->passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with('password123')
            ->willReturn('hashed-password');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getId() === 'user-123' &&
                       $user->getEmail() === 'john.doe@example.com' &&
                       $user->getPasswordHash() === 'hashed-password' &&
                       $user->getFirstName() === 'John' &&
                       $user->getLastName() === 'Doe';
            }));

        // Act
        $response = $this->createUser->execute($request);

        // Assert
        $this->assertInstanceOf(CreateUserResponse::class, $response);
        $this->assertEquals('user-123', $response->userId);
        $this->assertEquals('john.doe@example.com', $response->email);
        $this->assertEquals('John Doe', $response->fullName);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $response->createdAt);
    }

    public function testExecuteThrowsExceptionWhenEmailAlreadyExists(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'existing@example.com',
            'password123',
            'John',
            'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('existing@example.com')
            ->willReturn(true);

        $this->idGenerator->expects($this->never())->method('generate');
        $this->passwordHasher->expects($this->never())->method('hash');
        $this->userRepository->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(UserAlreadyExistsException::class);
        $this->expectExceptionMessage('Email already registered: existing@example.com');

        $this->createUser->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyEmail(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            '',
            'password123',
            'John',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is required');

        $this->createUser->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyPassword(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'john@example.com',
            '',
            'John',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password is required');

        $this->createUser->execute($request);
    }

    public function testExecuteThrowsExceptionForShortPassword(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'john@example.com',
            '123',
            'John',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');

        $this->createUser->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyFirstName(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'john@example.com',
            'password123',
            '',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name is required');

        $this->createUser->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyLastName(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'john@example.com',
            'password123',
            'John',
            ''
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name is required');

        $this->createUser->execute($request);
    }

    public function testExecuteNormalizesEmailToLowercase(): void
    {
        // Arrange
        $request = new CreateUserRequest(
            'John.DOE@EXAMPLE.COM',
            'password123',
            'John',
            'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('John.DOE@EXAMPLE.COM')
            ->willReturn(false);

        $this->idGenerator->method('generate')->willReturn('user-123');
        $this->passwordHasher->method('hash')->willReturn('hashed-password');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user): bool {
                return $user->getEmail() === 'john.doe@example.com';
            }));

        // Act
        $response = $this->createUser->execute($request);

        // Assert
        $this->assertEquals('john.doe@example.com', $response->email);
    }
}