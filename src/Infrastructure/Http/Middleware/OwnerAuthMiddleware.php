<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Middleware;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;

/**
 * Middleware d'authentification pour les propriétaires de parkings
 *
 * Vérifie que l'utilisateur authentifié est un owner (type='owner' dans le JWT)
 * Injecte l'ownerId dans les pathParams de la requête
 */
class OwnerAuthMiddleware
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
            // Authentifie et récupère les données du token JWT
            $headers = $request->getHeaders();
            $userData = $this->jwtAuthMiddleware->authenticate($headers);

            // Vérifie que le type est 'owner'
            if (!isset($userData['type']) || $userData['type'] !== 'owner') {
                return JsonResponse::forbidden('Access forbidden: Owner authentication required');
            }

            // Injecte ownerId dans pathParams pour que le controller puisse le récupérer
            // Note: On utilise des clés préfixées par _ pour éviter les conflits avec les vrais path params
            $request->setPathParams(array_merge(
                $request->getPathParams(),
                [
                    '_ownerId' => $userData['userId'],
                    '_ownerEmail' => $userData['email']
                ]
            ));

            // Ne retourne rien = continue vers le handler
            return null;

        } catch (\InvalidArgumentException $e) {
            // Token invalide ou manquant
            return JsonResponse::unauthorized($e->getMessage());
        }
    }
}
