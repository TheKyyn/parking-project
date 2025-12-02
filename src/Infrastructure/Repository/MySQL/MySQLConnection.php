<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

/**
 * MySQLConnection
 * Infrastructure Layer - MySQL database connection implementation
 */
class MySQLConnection implements MySQLConnectionInterface
{
    private ?\PDO $connection = null;

    public function __construct(
        private string $host,
        private string $database,
        private string $username,
        private string $password,
        private int $port = 3306
    ) {
    }

    public function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->database
            );

            $this->connection = new \PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        }

        return $this->connection;
    }

    public function beginTransaction(): void
    {
        $this->getConnection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getConnection()->commit();
    }

    public function rollback(): void
    {
        $this->getConnection()->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }
}
