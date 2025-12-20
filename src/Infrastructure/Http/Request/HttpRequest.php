<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Request;

/**
 * Représentation d'une requête HTTP
 */
class HttpRequest implements HttpRequestInterface
{
    private string $method;
    private string $path;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<string, mixed>|null */
    private ?array $body;
    /** @var array<string, string> */
    private array $queryParams;
    /** @var array<string, string> */
    private array $pathParams = [];

    /**
     * @param string $method Méthode HTTP
     * @param string $path Path de la requête
     * @param array<string, string> $headers Headers HTTP
     * @param array<string, mixed>|null $body Body parsé
     * @param array<string, string> $queryParams Query parameters
     */
    public function __construct(
        string $method,
        string $path,
        array $headers = [],
        ?array $body = null,
        array $queryParams = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $body;
        $this->queryParams = $queryParams;
    }

    /**
     * {@inheritDoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritDoc}
     */
    public function getQueryParam(string $name): ?string
    {
        return $this->queryParams[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /**
     * {@inheritDoc}
     */
    public function getPathParam(string $name): ?string
    {
        return $this->pathParams[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function setPathParams(array $params): void
    {
        $this->pathParams = $params;
    }

    /**
     * {@inheritDoc}
     */
    public function getContentType(): ?string
    {
        return $this->getHeader('content-type');
    }

    /**
     * {@inheritDoc}
     */
    public function isJson(): bool
    {
        $contentType = $this->getContentType();
        return $contentType !== null && str_contains($contentType, 'application/json');
    }

    /**
     * Normalise les headers (lowercase keys)
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }
        return $normalized;
    }

    /**
     * Crée une HttpRequest depuis les superglobals PHP
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Parse query params
        $queryParams = $_GET;

        // Parse headers
        $headers = self::parseHeaders();

        // Parse body
        $body = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $headers['content-type'] ?? '';

            if (str_contains($contentType, 'application/json')) {
                $rawBody = file_get_contents('php://input');
                if (!empty($rawBody)) {
                    $body = json_decode($rawBody, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \InvalidArgumentException('Invalid JSON body: ' . json_last_error_msg());
                    }
                }
            } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                $body = $_POST;
            }
        }

        return new self($method, $path, $headers, $body, $queryParams);
    }

    /**
     * Parse les headers HTTP depuis $_SERVER
     *
     * @return array<string, string>
     */
    private static function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            // Headers commencent par HTTP_
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerName)] = $value;
            }

            // Headers spéciaux
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headerName = str_replace('_', '-', $key);
                $headers[strtolower($headerName)] = $value;
            }
        }

        return $headers;
    }
}
