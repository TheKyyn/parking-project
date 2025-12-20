<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Routing;

/**
 * Exception levée quand aucune route ne correspond
 */
class RouteNotFoundException extends \Exception
{
    public function __construct(string $method, string $path)
    {
        parent::__construct("Route not found: {$method} {$path}", 404);
    }
}
