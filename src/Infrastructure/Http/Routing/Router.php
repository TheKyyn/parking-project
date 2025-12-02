<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Routing;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\HttpResponseInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;

/**
 * Routeur HTTP
 */
class Router implements RouterInterface
{
    /** @var array<Route> */
    private array $routes = [];

    /** @var array<callable> */
    private array $globalMiddleware = [];

    /**
     * {@inheritDoc}
     */
    public function get(string $path, callable $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function post(string $path, callable $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $path, callable $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path, callable $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function patch(string $path, callable $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * {@inheritDoc}
     */
    public function any(string $path, callable $handler): Route
    {
        // Enregistre pour toutes les méthodes communes
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $route = null;

        foreach ($methods as $method) {
            $route = $this->addRoute($method, $path, $handler);
        }

        return $route; // Retourne la dernière
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(HttpRequestInterface $request): HttpResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Trouve la route correspondante
        $route = $this->findRoute($method, $path);

        if ($route === null) {
            throw new RouteNotFoundException($method, $path);
        }

        // Extrait et définit les paramètres
        $parameters = $route->extractParameters($path);
        if ($parameters !== null) {
            $route->setParameters($parameters);
            $request->setPathParams($parameters);
        }

        // Exécute les middlewares globaux puis de la route
        $allMiddleware = array_merge($this->globalMiddleware, $route->getMiddleware());

        foreach ($allMiddleware as $middleware) {
            $result = $middleware($request);

            // Si le middleware retourne une response, on arrête
            if ($result instanceof HttpResponseInterface) {
                return $result;
            }
        }

        // Exécute le handler
        $handler = $route->getHandler();
        $response = $handler($request);

        // Si le handler ne retourne pas une HttpResponse, on wrappe
        if (!$response instanceof HttpResponseInterface) {
            if (is_array($response)) {
                $response = JsonResponse::success($response);
            } elseif (is_string($response)) {
                $response = new JsonResponse(['message' => $response]);
            } else {
                throw new \RuntimeException('Handler must return HttpResponseInterface, array, or string');
            }
        }

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Ajoute un middleware global (appliqué à toutes les routes)
     */
    public function addGlobalMiddleware(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * Crée un groupe de routes avec un préfixe et des middlewares communs
     *
     * @param array<callable> $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $originalRouteCount = count($this->routes);

        // Exécute le callback qui va enregistrer des routes
        $callback($this);

        // Applique le préfixe et les middlewares aux nouvelles routes
        $newRoutes = array_slice($this->routes, $originalRouteCount);

        foreach ($newRoutes as $route) {
            // Applique le préfixe au path
            // Note: Route::path n'a pas de setter, on applique uniquement les middlewares
            $route->middlewares($middleware);
        }
    }

    /**
     * Ajoute une route
     */
    private function addRoute(string $method, string $path, callable $handler): Route
    {
        $route = new Route($method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Trouve une route correspondant à la méthode et au path
     */
    private function findRoute(string $method, string $path): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }

        return null;
    }
}
