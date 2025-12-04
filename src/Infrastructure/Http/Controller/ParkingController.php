<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\Parking\CreateParking;
use ParkingSystem\UseCase\Parking\CreateParkingRequest;
use ParkingSystem\UseCase\Parking\UpdateParking;
use ParkingSystem\UseCase\Parking\UpdateParkingRequest;
use ParkingSystem\UseCase\Parking\DeleteParking;
use ParkingSystem\UseCase\Parking\DeleteParkingRequest;
use ParkingSystem\UseCase\Parking\ParkingNotFoundException;
use ParkingSystem\UseCase\Parking\UnauthorizedParkingAccessException;
use ParkingSystem\UseCase\Parking\OwnerNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\ValueObject\GpsCoordinates;

/**
 * Controller pour les endpoints Parking
 */
class ParkingController
{
    public function __construct(
        private CreateParking $createParkingUseCase,
        private UpdateParking $updateParkingUseCase,
        private DeleteParking $deleteParkingUseCase,
        private ParkingRepositoryInterface $parkingRepository
    ) {
    }

    /**
     * POST /api/parkings - Créer un nouveau parking (owner only)
     */
    public function create(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'ownerId depuis pathParams (injecté par OwnerAuthMiddleware)
            $ownerId = $request->getPathParam('_ownerId');

            if ($ownerId === null) {
                return JsonResponse::unauthorized('Owner authentication required');
            }

            // Récupère le body
            $body = $request->getBody();

            if ($body === null) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            // Validation
            $validator = new SimpleValidator();
            $errors = $validator->validate($body, [
                'name' => ['required', 'min:3'],
                'address' => ['required', 'min:5'],
                'latitude' => ['required', 'numeric'],
                'longitude' => ['required', 'numeric'],
                'hourlyRate' => ['required', 'numeric'],
                'totalSpots' => ['required', 'numeric'],
            ]);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Crée le request DTO
            $useCaseRequest = new CreateParkingRequest(
                $ownerId,
                $body['name'],
                $body['address'],
                (float)$body['latitude'],
                (float)$body['longitude'],
                (int)$body['totalSpots'],
                (float)$body['hourlyRate'],
                $body['openingHours'] ?? []
            );

            // Execute le use case
            $response = $this->createParkingUseCase->execute($useCaseRequest);

            // Retourne la réponse
            return JsonResponse::created([
                'parkingId' => $response->parkingId,
                'ownerId' => $response->ownerId,
                'name' => $response->name,
                'address' => $response->address,
                'location' => [
                    'latitude' => $response->latitude,
                    'longitude' => $response->longitude,
                ],
                'hourlyRate' => $response->hourlyRate,
                'totalSpots' => $response->totalSpaces,
                'createdAt' => $response->createdAt,
            ], 'Parking created successfully');

        } catch (OwnerNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while creating parking');
        }
    }

    /**
     * GET /api/parkings - Lister tous les parkings (ou rechercher avec GPS)
     */
    public function list(HttpRequestInterface $request): JsonResponse
    {
        try {
            $query = $request->getQueryParams();

            // Si latitude et longitude sont fournies, faire une recherche GPS
            if (isset($query['latitude']) && isset($query['longitude'])) {
                $latitude = (float)$query['latitude'];
                $longitude = (float)$query['longitude'];
                $maxDistance = isset($query['maxDistance']) ? (float)$query['maxDistance'] : 5.0;

                $location = new GpsCoordinates($latitude, $longitude);
                $parkings = $this->parkingRepository->findNearLocation($location, $maxDistance);
            } else {
                // Sinon, retourner tous les parkings
                $parkings = $this->parkingRepository->findAll();
            }

            // Formater la réponse
            $data = array_map(function ($parking) {
                return [
                    'id' => $parking->getId(),
                    'ownerId' => $parking->getOwnerId(),
                    'name' => $parking->getName(),
                    'address' => $parking->getAddress(),
                    'location' => [
                        'latitude' => $parking->getLatitude(),
                        'longitude' => $parking->getLongitude(),
                    ],
                    'hourlyRate' => $parking->getHourlyRate(),
                    'totalSpots' => $parking->getTotalSpaces(),
                    'createdAt' => $parking->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $parkings);

            return JsonResponse::success($data, 'Parkings retrieved successfully');

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving parkings');
        }
    }

    /**
     * GET /api/parkings/:id - Récupérer les détails d'un parking
     */
    public function show(HttpRequestInterface $request): JsonResponse
    {
        try {
            $parkingId = $request->getPathParam('id');

            if ($parkingId === null) {
                return JsonResponse::error('Parking ID is required', null, 400);
            }

            $parking = $this->parkingRepository->findById($parkingId);

            if ($parking === null) {
                return JsonResponse::notFound('Parking not found');
            }

            return JsonResponse::success([
                'id' => $parking->getId(),
                'ownerId' => $parking->getOwnerId(),
                'name' => $parking->getName(),
                'address' => $parking->getAddress(),
                'location' => [
                    'latitude' => $parking->getLatitude(),
                    'longitude' => $parking->getLongitude(),
                ],
                'hourlyRate' => $parking->getHourlyRate(),
                'totalSpots' => $parking->getTotalSpaces(),
                'createdAt' => $parking->getCreatedAt()->format('Y-m-d H:i:s'),
            ], 'Parking retrieved successfully');

        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving parking');
        }
    }

    /**
     * PUT /api/parkings/:id - Mettre à jour un parking (owner only)
     */
    public function update(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'ownerId depuis pathParams (injecté par OwnerAuthMiddleware)
            $ownerId = $request->getPathParam('_ownerId');

            if ($ownerId === null) {
                return JsonResponse::unauthorized('Owner authentication required');
            }

            $parkingId = $request->getPathParam('id');

            if ($parkingId === null) {
                return JsonResponse::error('Parking ID is required', null, 400);
            }

            // Récupère le body
            $body = $request->getBody();

            if ($body === null || empty($body)) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            // Validation (tous les champs sont optionnels)
            $validator = new SimpleValidator();
            $rules = [];
            if (isset($body['name'])) {
                $rules['name'] = ['min:3'];
            }
            if (isset($body['address'])) {
                $rules['address'] = ['min:5'];
            }
            if (isset($body['hourlyRate'])) {
                $rules['hourlyRate'] = ['numeric'];
            }
            if (isset($body['totalSpots'])) {
                $rules['totalSpots'] = ['numeric'];
            }

            $errors = $validator->validate($body, $rules);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Crée le request DTO
            $useCaseRequest = new UpdateParkingRequest(
                $parkingId,
                $ownerId,
                $body['name'] ?? null,
                $body['address'] ?? null,
                isset($body['totalSpots']) ? (int)$body['totalSpots'] : null,
                isset($body['hourlyRate']) ? (float)$body['hourlyRate'] : null,
                $body['openingHours'] ?? null
            );

            // Execute le use case
            $this->updateParkingUseCase->execute($useCaseRequest);

            // Récupère le parking mis à jour
            $parking = $this->parkingRepository->findById($parkingId);

            return JsonResponse::success([
                'id' => $parking->getId(),
                'ownerId' => $parking->getOwnerId(),
                'name' => $parking->getName(),
                'address' => $parking->getAddress(),
                'location' => [
                    'latitude' => $parking->getLatitude(),
                    'longitude' => $parking->getLongitude(),
                ],
                'hourlyRate' => $parking->getHourlyRate(),
                'totalSpots' => $parking->getTotalSpaces(),
                'createdAt' => $parking->getCreatedAt()->format('Y-m-d H:i:s'),
            ], 'Parking updated successfully');

        } catch (ParkingNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (UnauthorizedParkingAccessException $e) {
            return JsonResponse::forbidden('Unauthorized: You are not the owner of this parking');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while updating parking');
        }
    }

    /**
     * DELETE /api/parkings/:id - Supprimer un parking (owner only)
     */
    public function delete(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'ownerId depuis pathParams (injecté par OwnerAuthMiddleware)
            $ownerId = $request->getPathParam('_ownerId');

            if ($ownerId === null) {
                return JsonResponse::unauthorized('Owner authentication required');
            }

            $parkingId = $request->getPathParam('id');

            if ($parkingId === null) {
                return JsonResponse::error('Parking ID is required', null, 400);
            }

            // Crée le request DTO
            $useCaseRequest = new DeleteParkingRequest($parkingId, $ownerId);

            // Execute le use case
            $this->deleteParkingUseCase->execute($useCaseRequest);

            return JsonResponse::noContent();

        } catch (ParkingNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (UnauthorizedParkingAccessException $e) {
            return JsonResponse::forbidden('Unauthorized: You are not the owner of this parking');
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while deleting parking');
        }
    }
}
