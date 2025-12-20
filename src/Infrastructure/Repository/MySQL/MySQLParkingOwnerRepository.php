<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

use ParkingSystem\Domain\Entity\ParkingOwner;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;

/**
 * MySQLParkingOwnerRepository
 * Infrastructure Layer - MySQL implementation of ParkingOwnerRepositoryInterface
 */
class MySQLParkingOwnerRepository implements ParkingOwnerRepositoryInterface
{
    public function __construct(
        private MySQLConnectionInterface $connection
    ) {
    }

    public function save(ParkingOwner $owner): void
    {
        $pdo = $this->connection->getConnection();

        $sql = 'INSERT INTO parking_owners (id, email, password_hash, first_name, last_name, created_at)
                VALUES (:id, :email, :password_hash, :first_name, :last_name, :created_at)
                ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                password_hash = VALUES(password_hash),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $owner->getId(),
            'email' => $owner->getEmail(),
            'password_hash' => $owner->getPasswordHash(),
            'first_name' => $owner->getFirstName(),
            'last_name' => $owner->getLastName(),
            'created_at' => $owner->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function findById(string $id): ?ParkingOwner
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_owners WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateOwner($row);
    }

    public function findByEmail(string $email): ?ParkingOwner
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_owners WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateOwner($row);
    }

    public function findAll(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT * FROM parking_owners ORDER BY created_at DESC');
        $owners = [];

        while ($row = $stmt->fetch()) {
            $owners[] = $this->hydrateOwner($row);
        }

        return $owners;
    }

    public function delete(ParkingOwner $owner): void
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('DELETE FROM parking_owners WHERE id = :id');
        $stmt->execute(['id' => $owner->getId()]);
    }

    public function exists(string $id): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM parking_owners WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    public function count(): int
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM parking_owners');
        $result = $stmt->fetch();

        return (int)$result['count'];
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $pdo = $this->connection->getConnection();

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM parking_owners WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $owners = [];
        while ($row = $stmt->fetch()) {
            $owners[] = $this->hydrateOwner($row);
        }

        return $owners;
    }

    public function emailExists(string $email): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM parking_owners WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);

        return $stmt->fetch() !== false;
    }

    public function findRecentlyCreated(int $limit = 10): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_owners ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $owners = [];
        while ($row = $stmt->fetch()) {
            $owners[] = $this->hydrateOwner($row);
        }

        return $owners;
    }

    public function findWithMostParkings(int $limit = 10): array
    {
        // Simple implementation - returns all owners
        // Can be enhanced later with JOIN on parkings table
        return array_slice($this->findAll(), 0, $limit);
    }

    private function hydrateOwner(array $row): ParkingOwner
    {
        return new ParkingOwner(
            $row['id'],
            $row['email'],
            $row['password_hash'],
            $row['first_name'],
            $row['last_name'],
            new \DateTimeImmutable($row['created_at'])
        );
    }
}
