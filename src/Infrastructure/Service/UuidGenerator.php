<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\User\IdGeneratorInterface as UserIdGeneratorInterface;
use ParkingSystem\UseCase\Parking\IdGeneratorInterface as ParkingIdGeneratorInterface;
use ParkingSystem\UseCase\Reservation\IdGeneratorInterface as ReservationIdGeneratorInterface;
use ParkingSystem\UseCase\Session\IdGeneratorInterface as SessionIdGeneratorInterface;

/**
 * UUID v4 identifier generator
 */
class UuidGenerator implements
    UserIdGeneratorInterface,
    ParkingIdGeneratorInterface,
    ReservationIdGeneratorInterface,
    SessionIdGeneratorInterface
{
    /**
     * Generate a UUID v4 (random)
     *
     * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     * where x is hexadecimal and y is 8, 9, A or B
     *
     * @return string UUID v4
     */
    public function generate(): string
    {
        $data = random_bytes(16);

        // Set version (4) - bits 12-15 of time_hi_and_version
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);

        // Set variant (RFC 4122) - bits 6-7 of clock_seq_hi_and_reserved
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
