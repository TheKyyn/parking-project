<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * GenerateInvoiceRequest DTO
 */
class GenerateInvoiceRequest
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $userId,
        public readonly string $format = 'html' // 'html' or 'pdf'
    ) {
    }
}
