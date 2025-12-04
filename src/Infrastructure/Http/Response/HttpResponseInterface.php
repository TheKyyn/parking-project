<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Response;

/**
 * Interface pour représenter une réponse HTTP
 */
interface HttpResponseInterface
{
    /**
     * Récupère le status code HTTP
     */
    public function getStatusCode(): int;

    /**
     * Récupère les headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Récupère le body
     *
     * @return array<string, mixed>|string|null
     */
    public function getBody(): array|string|null;

    /**
     * Envoie la réponse au client
     */
    public function send(): void;

    /**
     * Convertit en JSON
     */
    public function toJson(): string;
}
