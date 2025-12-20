<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Middleware;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;

/**
 * Middleware CORS (Cross-Origin Resource Sharing)
 *
 * Permet aux frontends hébergés sur d'autres domaines d'accéder à l'API
 */
class CorsMiddleware
{
    /** @var array<string> */
    private array $allowedOrigins;
    /** @var array<string> */
    private array $allowedMethods;
    /** @var array<string> */
    private array $allowedHeaders;
    private bool $allowCredentials;
    private int $maxAge;

    /**
     * @param array<string> $allowedOrigins Origines autorisées (ex: ['https://app.com'])
     * @param array<string> $allowedMethods Méthodes HTTP autorisées
     * @param array<string> $allowedHeaders Headers autorisés
     * @param bool $allowCredentials Autoriser les credentials (cookies)
     * @param int $maxAge Durée de cache preflight en secondes
     */
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $allowCredentials = true,
        int $maxAge = 86400
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
        $this->maxAge = $maxAge;
    }

    /**
     * Applique les headers CORS
     */
    public function handle(HttpRequestInterface $request): void
    {
        $origin = $request->getHeader('origin');

        // Si pas d'origin, c'est une requête same-origin (pas besoin de CORS)
        if ($origin === null) {
            return;
        }

        // Vérifie si l'origin est autorisée
        if (!$this->isOriginAllowed($origin)) {
            return;
        }

        // Set CORS headers
        header('Access-Control-Allow-Origin: ' . $origin);

        if ($this->allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        // Preflight request (OPTIONS)
        if ($request->getMethod() === 'OPTIONS') {
            header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
            header('Access-Control-Max-Age: ' . $this->maxAge);

            http_response_code(204);
            exit;
        }
    }

    /**
     * Vérifie si une origin est autorisée
     */
    private function isOriginAllowed(string $origin): bool
    {
        // Si '*' est dans la liste, tout est autorisé
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Crée un middleware CORS permissif (développement)
     */
    public static function permissive(): self
    {
        return new self(
            allowedOrigins: ['*'],
            allowedMethods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            allowedHeaders: ['*'],
            allowCredentials: true
        );
    }

    /**
     * Crée un middleware CORS restrictif (production)
     *
     * @param array<string> $allowedOrigins
     */
    public static function restrictive(array $allowedOrigins): self
    {
        return new self(
            allowedOrigins: $allowedOrigins,
            allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
            allowedHeaders: ['Content-Type', 'Authorization'],
            allowCredentials: true,
            maxAge: 3600
        );
    }
}
