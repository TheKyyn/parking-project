<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

use ParkingSystem\Domain\Entity\User;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * MySQLUserRepository
 * Infrastructure Layer - MySQL implementation of UserRepositoryInterface
 */
class MySQLUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private MySQLConnectionInterface $connection
    ) {
    }

    public function save(User $user): void
    {
        $pdo = $this->connection->getConnection();

        $sql = 'INSERT INTO users (id, email, password_hash, first_name, last_name, created_at)
                VALUES (:id, :email, :password_hash, :first_name, :last_name, :created_at)
                ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                password_hash = VALUES(password_hash),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'password_hash' => $user->getPasswordHash(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function findById(string $id): ?User
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    public function findByEmail(string $email): ?User
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateUser($row);
    }

    public function findAll(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
        $users = [];

        while ($row = $stmt->fetch()) {
            $users[] = $this->hydrateUser($row);
        }

        return $users;
    }

    public function delete(User $user): void
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $user->getId()]);
    }

    public function exists(string $id): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    public function count(): int
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $users = [];
        while ($row = $stmt->fetch()) {
            $users[] = $this->hydrateUser($row);
        }

        return $users;
    }

    private function hydrateUser(array $row): User
    {
        return new User(
            $row['id'],
            $row['email'],
            $row['password_hash'],
            $row['first_name'],
            $row['last_name'],
            new \DateTimeImmutable($row['created_at'])
        );
    }
}
