<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\File;

/**
 * AbstractFileRepository
 * Infrastructure Layer - Base class for file-based repositories
 */
abstract class AbstractFileRepository
{
    protected string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/');
        $this->ensureStorageDirectoryExists();
    }

    protected function ensureStorageDirectoryExists(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    protected function getFilePath(): string
    {
        return $this->storagePath . '/' . $this->getFileName();
    }

    protected function loadData(): array
    {
        $filePath = $this->getFilePath();

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    protected function saveData(array $data): void
    {
        $filePath = $this->getFilePath();

        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($filePath, $content, LOCK_EX);
    }

    abstract protected function getFileName(): string;
}
