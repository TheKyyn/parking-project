<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

/**
 * MySQLConnectionInterface
 * Infrastructure Layer - Contract for MySQL database connection
 */
interface MySQLConnectionInterface
{
    public function getConnection(): \PDO;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;
}
