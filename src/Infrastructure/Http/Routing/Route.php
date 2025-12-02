<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Routing;

/**
 * Représente une route HTTP
 */
class Route
{
    private string $method;
    private string $path;
    /** @var callable */
    private $handler;
    /** @var array<string, string> */
    private array $parameters = [];
    /** @var array<callable> */
    private array $middleware = [];
    private ?string $name = null;

    /**
     * @param string $method Méthode HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $path Pattern de la route (/users/:id)
     * @param callable $handler Callback du controller
     */
    public function __construct(string $method, string $path, callable $handler)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): callable
    {
        return $this->handler;
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array<callable>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Ajoute un middleware à la route
     *
     * @param callable $middleware
     * @return self Pour chaining fluent
     */
    public function middleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Ajoute plusieurs middlewares
     *
     * @param array<callable> $middlewares
     * @return self
     */
    public function middlewares(array $middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middleware($middleware);
        }
        return $this;
    }

    /**
     * Définit le nom de la route
     *
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Vérifie si la route correspond à une méthode et un path
     *
     * @return bool True si match
     */
    public function matches(string $method, string $path): bool
    {
        if ($this->method !== strtoupper($method)) {
            return false;
        }

        $pattern = $this->convertToRegex($this->path);
        return preg_match($pattern, $path) === 1;
    }

    /**
     * Extrait les paramètres d'un path
     *
     * @return array<string, string>|null Null si pas de match
     */
    public function extractParameters(string $path): ?array
    {
        $pattern = $this->convertToRegex($this->path);

        if (preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        // Retire le match complet
        array_shift($matches);

        // Récupère les noms des paramètres
        preg_match_all('/:([a-zA-Z0-9_]+)/', $this->path, $paramNames);
        $paramNames = $paramNames[1];

        // Associe noms et valeurs
        $parameters = [];
        foreach ($paramNames as $index => $name) {
            $parameters[$name] = $matches[$index] ?? '';
        }

        return $parameters;
    }

    /**
     * Convertit un path avec paramètres en regex
     *
     * Exemples:
     * /users/:id => /^\/users\/([^\/]+)$/
     * /parkings/:parkingId/sessions/:sessionId => /^\/parkings\/([^\/]+)\/sessions\/([^\/]+)$/
     */
    private function convertToRegex(string $path): string
    {
        // Échappe les slashes et autres caractères spéciaux
        $pattern = preg_quote($path, '/');

        // Remplace :param par un groupe de capture
        $pattern = preg_replace('/\\\\:([a-zA-Z0-9_]+)/', '([^\/]+)', $pattern);

        return '/^' . $pattern . '$/';
    }
}
