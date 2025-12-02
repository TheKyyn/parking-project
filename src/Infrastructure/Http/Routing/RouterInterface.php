<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Routing;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\HttpResponseInterface;

/**
 * Interface pour le routeur HTTP
 */
interface RouterInterface
{
    /**
     * Enregistre une route GET
     */
    public function get(string $path, callable $handler): Route;

    /**
     * Enregistre une route POST
     */
    public function post(string $path, callable $handler): Route;

    /**
     * Enregistre une route PUT
     */
    public function put(string $path, callable $handler): Route;

    /**
     * Enregistre une route DELETE
     */
    public function delete(string $path, callable $handler): Route;

    /**
     * Enregistre une route PATCH
     */
    public function patch(string $path, callable $handler): Route;

    /**
     * Enregistre une route pour toutes les méthodes
     */
    public function any(string $path, callable $handler): Route;

    /**
     * Dispatch une requête vers le handler approprié
     *
     * @throws RouteNotFoundException Si aucune route ne correspond
     */
    public function dispatch(HttpRequestInterface $request): HttpResponseInterface;

    /**
     * Récupère toutes les routes enregistrées
     *
     * @return array<Route>
     */
    public function getRoutes(): array;
}
