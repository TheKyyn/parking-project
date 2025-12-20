<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * MigrationRunner
 * Infrastructure Layer - Executes database migrations
 */
class MigrationRunner
{
    private \PDO $connection;
    private array $migrations = [];
    private string $migrationsTable = 'schema_migrations';

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
        $this->ensureMigrationsTableExists();
    }

    public function registerMigration(MigrationInterface $migration): void
    {
        $this->migrations[$migration->getVersion()] = $migration;
    }

    public function registerMigrations(array $migrations): void
    {
        foreach ($migrations as $migration) {
            $this->registerMigration($migration);
        }
    }

    public function migrateUp(?string $targetVersion = null): array
    {
        $executed = [];
        $appliedVersions = $this->getAppliedVersions();

        // Sort migrations by version
        ksort($this->migrations);

        foreach ($this->migrations as $version => $migration) {
            if (in_array($version, $appliedVersions, true)) {
                continue;
            }

            if ($targetVersion !== null && $version > $targetVersion) {
                break;
            }

            $this->executeMigrationUp($migration);
            $executed[] = [
                'version' => $version,
                'description' => $migration->getDescription()
            ];
        }

        return $executed;
    }

    public function migrateDown(?string $targetVersion = null): array
    {
        $rolledBack = [];
        $appliedVersions = $this->getAppliedVersions();

        // Sort migrations by version descending
        krsort($this->migrations);

        foreach ($this->migrations as $version => $migration) {
            if (!in_array($version, $appliedVersions, true)) {
                continue;
            }

            if ($targetVersion !== null && $version <= $targetVersion) {
                break;
            }

            $this->executeMigrationDown($migration);
            $rolledBack[] = [
                'version' => $version,
                'description' => $migration->getDescription()
            ];
        }

        return $rolledBack;
    }

    public function rollback(int $steps = 1): array
    {
        $rolledBack = [];
        $appliedVersions = $this->getAppliedVersions();

        // Sort applied versions descending
        rsort($appliedVersions);

        $count = 0;
        foreach ($appliedVersions as $version) {
            if ($count >= $steps) {
                break;
            }

            if (!isset($this->migrations[$version])) {
                continue;
            }

            $migration = $this->migrations[$version];
            $this->executeMigrationDown($migration);

            $rolledBack[] = [
                'version' => $version,
                'description' => $migration->getDescription()
            ];

            $count++;
        }

        return $rolledBack;
    }

    public function getStatus(): array
    {
        $appliedVersions = $this->getAppliedVersions();
        $status = [];

        ksort($this->migrations);

        foreach ($this->migrations as $version => $migration) {
            $status[] = [
                'version' => $version,
                'description' => $migration->getDescription(),
                'applied' => in_array($version, $appliedVersions, true)
            ];
        }

        return $status;
    }

    public function getPendingMigrations(): array
    {
        $appliedVersions = $this->getAppliedVersions();
        $pending = [];

        foreach ($this->migrations as $version => $migration) {
            if (!in_array($version, $appliedVersions, true)) {
                $pending[] = [
                    'version' => $version,
                    'description' => $migration->getDescription()
                ];
            }
        }

        return $pending;
    }

    private function ensureMigrationsTableExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            version VARCHAR(50) PRIMARY KEY,
            applied_at DATETIME NOT NULL,
            description VARCHAR(255)
        )";

        $this->connection->exec($sql);
    }

    private function getAppliedVersions(): array
    {
        $stmt = $this->connection->query(
            "SELECT version FROM {$this->migrationsTable} ORDER BY version"
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function executeMigrationUp(MigrationInterface $migration): void
    {
        try {
            $migration->up($this->connection);

            $stmt = $this->connection->prepare(
                "INSERT INTO {$this->migrationsTable} (version, applied_at, description) VALUES (?, NOW(), ?)"
            );
            $stmt->execute([$migration->getVersion(), $migration->getDescription()]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Migration {$migration->getVersion()} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function executeMigrationDown(MigrationInterface $migration): void
    {
        try {
            $migration->down($this->connection);

            $stmt = $this->connection->prepare(
                "DELETE FROM {$this->migrationsTable} WHERE version = ?"
            );
            $stmt->execute([$migration->getVersion()]);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Rollback of {$migration->getVersion()} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
