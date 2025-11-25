<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use ParkingSystem\UseCase\User\CreateUserRequest;
use ParkingSystem\UseCase\User\CreateUserResponse;

/**
 * CreateUserDtoTest
 * Unit tests for CreateUser DTOs
 */
class CreateUserDtoTest extends TestCase
{
    public function testCreateUserRequestHoldsData(): void
    {
        // Arrange & Act
        $request = new CreateUserRequest(
            'test@example.com',
            'password123',
            'John',
            'Doe'
        );

        // Assert
        $this->assertEquals('test@example.com', $request->email);
        $this->assertEquals('password123', $request->password);
        $this->assertEquals('John', $request->firstName);
        $this->assertEquals('Doe', $request->lastName);
    }

    public function testCreateUserResponseHoldsData(): void
    {
        // Arrange & Act
        $response = new CreateUserResponse(
            'user-123',
            'test@example.com',
            'John Doe',
            '2024-11-24T14:30:00+00:00'
        );

        // Assert
        $this->assertEquals('user-123', $response->userId);
        $this->assertEquals('test@example.com', $response->email);
        $this->assertEquals('John Doe', $response->fullName);
        $this->assertEquals('2024-11-24T14:30:00+00:00', $response->createdAt);
    }
}