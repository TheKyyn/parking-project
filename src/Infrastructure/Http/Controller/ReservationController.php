<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\Reservation\CreateReservation;
use ParkingSystem\UseCase\Reservation\CreateReservationRequest;
use ParkingSystem\UseCase\Reservation\CancelReservation;
use ParkingSystem\UseCase\Reservation\GenerateInvoice;
use ParkingSystem\UseCase\Reservation\GenerateInvoiceRequest;
use ParkingSystem\UseCase\Reservation\ReservationNotFoundException;
use ParkingSystem\UseCase\Reservation\UnauthorizedReservationAccessException;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;

/**
 * Controller pour les endpoints Reservation
 */
class ReservationController
{
    public function __construct(
        private CreateReservation $createReservationUseCase,
        private CancelReservation $cancelReservationUseCase,
        private ?GenerateInvoice $generateInvoiceUseCase,
        private ReservationRepositoryInterface $reservationRepository,
        private \ParkingSystem\Domain\Repository\ParkingRepositoryInterface $parkingRepository,
        private \ParkingSystem\Domain\Repository\UserRepositoryInterface $userRepository,
        private ?ParkingSessionRepositoryInterface $sessionRepository = null
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

    /**
     * GET /api/owner/reservations - Liste toutes les réservations des parkings du propriétaire (owner auth)
     */
    public function ownerIndex(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'ownerId depuis le middleware
            $ownerId = $request->getPathParam('_ownerId');

            if ($ownerId === null) {
                return JsonResponse::unauthorized('Owner authentication required');
            }

            // Récupère tous les parkings du propriétaire
            $parkings = $this->parkingRepository->findByOwnerId($ownerId);
            $parkingIds = array_map(fn($p) => $p->getId(), $parkings);

            if (empty($parkingIds)) {
                return JsonResponse::success([], 'No parkings found for this owner');
            }

            // Récupère toutes les réservations pour ces parkings
            $reservations = $this->reservationRepository->findByParkingIds($parkingIds);

            // Formate la réponse avec données user et parking jointes
            $reservationsArray = array_map(function ($reservation) {
                $user = $this->userRepository->findById($reservation->getUserId());
                $parking = $this->parkingRepository->findById($reservation->getParkingId());

                return [
                    'id' => $reservation->getId(),
                    'userId' => $reservation->getUserId(),
                    'parkingId' => $reservation->getParkingId(),
                    'user' => $user ? [
                        'id' => $user->getId(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName(),
                        'email' => $user->getEmail(),
                    ] : null,
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

            return JsonResponse::success($reservationsArray, 'Owner reservations retrieved successfully');

        } catch (\Exception $e) {
            error_log('Error fetching owner reservations: ' . $e->getMessage());
            return JsonResponse::serverError('An error occurred while retrieving owner reservations');
        }
    }

    /**
     * GET /api/reservations/:id/invoice - Get invoice for a reservation (user auth)
     */
    public function invoice(HttpRequestInterface $request): JsonResponse
    {
        try {
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $reservationId = $request->getPathParam('id');

            if ($reservationId === null) {
                return JsonResponse::error('Reservation ID is required', null, 400);
            }

            if ($this->generateInvoiceUseCase === null) {
                return JsonResponse::serverError('Invoice generation not available');
            }

            $invoiceRequest = new GenerateInvoiceRequest($reservationId, $userId);
            $invoiceResponse = $this->generateInvoiceUseCase->execute($invoiceRequest);

            // Check format query param
            $query = $request->getQueryParams();
            $format = $query['format'] ?? 'json';

            if ($format === 'html') {
                return new JsonResponse(
                    200,
                    ['Content-Type' => 'text/html'],
                    $invoiceResponse->toHtml()
                );
            }

            return JsonResponse::success($invoiceResponse->toArray(), 'Invoice generated');

        } catch (ReservationNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (UnauthorizedReservationAccessException $e) {
            return JsonResponse::forbidden($e->getMessage());
        } catch (\Exception $e) {
            return JsonResponse::serverError('Error generating invoice');
        }
    }
}
