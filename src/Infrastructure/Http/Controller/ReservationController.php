<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\Reservation\CreateReservation;
use ParkingSystem\UseCase\Reservation\CreateReservationRequest;
use ParkingSystem\UseCase\Reservation\CancelReservation;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;

/**
 * Controller pour les endpoints Reservation
 */
class ReservationController
{
    public function __construct(
        private CreateReservation $createReservationUseCase,
        private CancelReservation $cancelReservationUseCase,
        private ReservationRepositoryInterface $reservationRepository,
        private \ParkingSystem\Domain\Repository\ParkingRepositoryInterface $parkingRepository
    ) {
    }

    /**
     * POST /api/reservations - Créer une réservation (user auth required)
     */
    public function create(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            // Récupère le body
            $body = $request->getBody();

            if ($body === null) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            // Validation
            $validator = new SimpleValidator();
            $errors = $validator->validate($body, [
                'parkingId' => ['required'],
                'startTime' => ['required'],
                'endTime' => ['required'],
            ]);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Parse les dates
            try {
                $startTime = new \DateTimeImmutable($body['startTime']);
                $endTime = new \DateTimeImmutable($body['endTime']);
            } catch (\Exception $e) {
                return JsonResponse::error('Invalid date format. Use ISO 8601 format (e.g., 2025-12-04T10:00:00)', null, 400);
            }

            // Crée le request DTO
            $useCaseRequest = new CreateReservationRequest(
                $userId,
                $body['parkingId'],
                $startTime,
                $endTime
            );

            // Execute le use case
            $response = $this->createReservationUseCase->execute($useCaseRequest);

            // Retourne la réponse
            return JsonResponse::created(
                [
                    'reservationId' => $response->reservationId,
                    'userId' => $response->userId,
                    'parkingId' => $response->parkingId,
                    'startTime' => $response->startTime,
                    'endTime' => $response->endTime,
                    'totalAmount' => $response->totalAmount,
                    'durationMinutes' => $response->durationMinutes,
                    'status' => $response->status,
                ],
                'Reservation created successfully'
            );

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while creating reservation: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/reservations - Liste les réservations de l'utilisateur (user auth)
     */
    public function index(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            // Récupère les réservations de l'utilisateur
            $reservations = $this->reservationRepository->findByUserId($userId);

            // Formate la réponse avec données parking jointes
            $reservationsArray = array_map(function ($reservation) {
                $parking = $this->parkingRepository->findById($reservation->getParkingId());

                return [
                    'id' => $reservation->getId(),
                    'userId' => $reservation->getUserId(),
                    'parkingId' => $reservation->getParkingId(),
                    'parking' => $parking ? [
                        'id' => $parking->getId(),
                        'name' => $parking->getName(),
                        'address' => $parking->getAddress(),
                        'hourlyRate' => $parking->getHourlyRate(),
                    ] : null,
                    'startTime' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
                    'endTime' => $reservation->getEndTime()->format('Y-m-d H:i:s'),
                    'totalAmount' => $reservation->getTotalAmount(),
                    'status' => $reservation->getStatus(),
                    'createdAt' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $reservations);

            return JsonResponse::success($reservationsArray, 'Reservations retrieved successfully');

        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving reservations');
        }
    }

    /**
     * GET /api/reservations/:id - Détails d'une réservation (user auth)
     */
    public function show(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $reservationId = $request->getPathParam('id');

            if ($reservationId === null) {
                return JsonResponse::error('Reservation ID is required', null, 400);
            }

            // Récupère la réservation
            $reservation = $this->reservationRepository->findById($reservationId);

            if ($reservation === null) {
                return JsonResponse::notFound('Reservation not found');
            }

            // Vérifie que c'est bien la réservation de l'utilisateur
            if ($reservation->getUserId() !== $userId) {
                return JsonResponse::forbidden('Unauthorized: This is not your reservation');
            }

            // Retourne la réservation
            return JsonResponse::success([
                'id' => $reservation->getId(),
                'userId' => $reservation->getUserId(),
                'parkingId' => $reservation->getParkingId(),
                'startTime' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $reservation->getEndTime()->format('Y-m-d H:i:s'),
                'totalAmount' => $reservation->getTotalAmount(),
                'status' => $reservation->getStatus(),
                'createdAt' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            ], 'Reservation retrieved successfully');

        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving reservation');
        }
    }

    /**
     * DELETE /api/reservations/:id - Annuler une réservation (user auth, pas encore commencée)
     */
    public function cancel(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $reservationId = $request->getPathParam('id');

            if ($reservationId === null) {
                return JsonResponse::error('Reservation ID is required', null, 400);
            }

            // Execute le use case
            $this->cancelReservationUseCase->execute($reservationId, $userId);

            // Retourne no content
            return JsonResponse::noContent();

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while cancelling reservation');
        }
    }
}
