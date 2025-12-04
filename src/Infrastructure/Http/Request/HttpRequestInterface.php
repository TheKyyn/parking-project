<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Request;

/**
 * Interface pour représenter une requête HTTP
 */
interface HttpRequestInterface
{
    /**
     * Récupère la méthode HTTP (GET, POST, PUT, DELETE)
     */
    public function getMethod(): string;

    /**
     * Récupère le path de la requête (ex: /api/users)
     */
    public function getPath(): string;

    /**
     * Récupère tous les headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Récupère un header spécifique
     */
    public function getHeader(string $name): ?string;

    /**
     * Récupère le body parsé (JSON décodé)
     *
     * @return array<string, mixed>|null
     */
    public function getBody(): ?array;

    /**
     * Récupère les query parameters (?key=value)
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array;

    /**
     * Récupère un query parameter spécifique
     */
    public function getQueryParam(string $name): ?string;

    /**
     * Récupère les path parameters (ex: /users/:id)
     *
     * @return array<string, string>
     */
    public function getPathParams(): array;

    /**
     * Récupère un path parameter spécifique
     */
    public function getPathParam(string $name): ?string;

    /**
     * Définit les path parameters (appelé par le router)
     *
     * @param array<string, string> $params
     */
    public function setPathParams(array $params): void;

    /**
     * Récupère le Content-Type
     */
    public function getContentType(): ?string;

    /**
     * Vérifie si la requête est en JSON
     */
    public function isJson(): bool;
}
