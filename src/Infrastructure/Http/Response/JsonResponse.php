<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Response;

/**
 * Réponse HTTP au format JSON
 */
class JsonResponse implements HttpResponseInterface
{
    private int $statusCode;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<string, mixed> */
    private array $body;

    /**
     * @param array<string, mixed> $data Données à retourner
     * @param int $statusCode Status code HTTP
     * @param array<string, string> $headers Headers additionnels
     */
    public function __construct(
        array $data,
        int $statusCode = 200,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->body = $data;
        $this->headers = array_merge(
            ['Content-Type' => 'application/json'],
            $headers
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
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
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * {@inheritDoc}
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Output body
        echo $this->toJson();
    }

    /**
     * {@inheritDoc}
     */
    public function toJson(): string
    {
        return json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Crée une réponse de succès
     *
     * @param array<string, mixed>|null $data
     */
    public static function success(?array $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Crée une réponse d'erreur
     *
     * @param array<string, array<string>>|null $errors
     */
    public static function error(string $message, ?array $errors = null, int $statusCode = 400): self
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new self($body, $statusCode);
    }

    /**
     * Crée une réponse 201 Created
     *
     * @param array<string, mixed> $data
     */
    public static function created(array $data, string $message = 'Resource created'): self
    {
        return self::success($data, $message, 201);
    }

    /**
     * Crée une réponse 204 No Content
     */
    public static function noContent(): self
    {
        return new self([], 204);
    }

    /**
     * Crée une réponse 404 Not Found
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, null, 404);
    }

    /**
     * Crée une réponse 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, null, 401);
    }

    /**
     * Crée une réponse 403 Forbidden
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, null, 403);
    }

    /**
     * Crée une réponse 422 Unprocessable Entity (validation errors)
     *
     * @param array<string, array<string>> $errors
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): self
    {
        return self::error($message, $errors, 422);
    }

    /**
     * Crée une réponse 500 Internal Server Error
     */
    public static function serverError(string $message = 'Internal server error'): self
    {
        return self::error($message, null, 500);
    }
}
