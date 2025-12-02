<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * ListUnauthorizedUsersResponse DTO
 * Use Case Layer - Output for listing unauthorized parked users
 */
class ListUnauthorizedUsersResponse
{
    /**
     * @param UnauthorizedUserInfo[] $unauthorizedUsers
     */
    public function __construct(
        public readonly string $parkingId,
        public readonly string $checkedAt,
        public readonly int $totalUnauthorized,
        public readonly array $unauthorizedUsers
    ) {
    }
}
