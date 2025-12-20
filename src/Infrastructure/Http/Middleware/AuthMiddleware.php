<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Middleware;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;

/**
 * Middleware d'authentification pour le Router
 *
 * Extrait l'userId du JWT et l'injecte dans les pathParams de la requête
 */
class AuthMiddleware
{
    public function __construct(
        private JwtAuthMiddleware $jwtAuthMiddleware
    ) {
    }

    /**
     * Middleware callable pour le Router
     *
     * @return JsonResponse|null Retourne null pour continuer, JsonResponse pour arrêter
     */
    public function __invoke(HttpRequestInterface $request): ?JsonResponse
    {
        try {
            // Authentifie et récupère les données user
            $headers = $request->getHeaders();
            $userData = $this->jwtAuthMiddleware->authenticate($headers);

            // Injecte userId dans pathParams pour que le controller puisse le récupérer
            // Note: On utilise des clés préfixées par _ pour éviter les conflits avec les vrais path params
            $request->setPathParams(array_merge(
                $request->getPathParams(),
                [
                    '_userId' => $userData['userId'],
                    '_userEmail' => $userData['email']
                ]
            ));

            // Ne retourne rien = continue vers le handler
            return null;

        } catch (\InvalidArgumentException $e) {
            // Retourne une erreur 401 = arrête le traitement
            return JsonResponse::unauthorized($e->getMessage());
        }
    }
}
