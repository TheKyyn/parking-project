<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\Session\EnterParking;
use ParkingSystem\UseCase\Session\EnterParkingRequest;
use ParkingSystem\UseCase\Session\ExitParking;
use ParkingSystem\UseCase\Session\ExitParkingRequest;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;

/**
 * Controller pour les endpoints Session
 */
class SessionController
{
    public function __construct(
        private EnterParking $enterParkingUseCase,
        private ExitParking $exitParkingUseCase,
        private ParkingSessionRepositoryInterface $sessionRepository
    ) {
    }

    /**
     * POST /api/sessions - Démarrer une session (user auth required)
     */
    public function start(HttpRequestInterface $request): JsonResponse
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
            ]);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Crée le request DTO
            $useCaseRequest = new EnterParkingRequest(
                $userId,
                $body['parkingId']
            );

            // Execute le use case
            $response = $this->enterParkingUseCase->execute($useCaseRequest);

            // Retourne la réponse
            return JsonResponse::created(
                [
                    'sessionId' => $response->sessionId,
                    'userId' => $response->userId,
                    'parkingId' => $response->parkingId,
                    'reservationId' => $response->reservationId,
                    'startTime' => $response->startTime,
                    'authorizedEndTime' => $response->authorizedEndTime,
                    'status' => $response->status,
                ],
                'Session started successfully'
            );

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while starting session: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/sessions/:id/end - Terminer une session (user auth required)
     */
    public function end(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $sessionId = $request->getPathParam('id');

            if ($sessionId === null) {
                return JsonResponse::error('Session ID is required', null, 400);
            }

            // Vérifie que la session existe et appartient à l'utilisateur
            $session = $this->sessionRepository->findById($sessionId);

            if ($session === null) {
                return JsonResponse::notFound('Session not found');
            }

            if ($session->getUserId() !== $userId) {
                return JsonResponse::forbidden('Unauthorized: This is not your session');
            }

            // Crée le request DTO
            $useCaseRequest = new ExitParkingRequest($sessionId);

            // Execute le use case
            $response = $this->exitParkingUseCase->execute($useCaseRequest);

            // Retourne la réponse
            return JsonResponse::success(
                [
                    'sessionId' => $response->sessionId,
                    'userId' => $response->userId,
                    'parkingId' => $response->parkingId,
                    'startTime' => $response->startTime,
                    'endTime' => $response->endTime,
                    'durationMinutes' => $response->durationMinutes,
                    'baseAmount' => $response->baseAmount,
                    'overstayPenalty' => $response->overstayPenalty,
                    'totalAmount' => $response->totalAmount,
                    'wasOverstayed' => $response->wasOverstayed,
                    'status' => $response->status,
                ],
                'Session ended successfully'
            );

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\DomainException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while ending session: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/sessions - Liste les sessions de l'utilisateur (user auth)
     */
    public function index(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            // Récupère les sessions de l'utilisateur
            $sessions = $this->sessionRepository->findByUserId($userId);

            // Formate la réponse
            $sessionsArray = array_map(function ($session) {
                return [
                    'id' => $session->getId(),
                    'userId' => $session->getUserId(),
                    'parkingId' => $session->getParkingId(),
                    'reservationId' => $session->getReservationId(),
                    'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                    'endTime' => $session->getEndTime()?->format('Y-m-d H:i:s'),
                    'totalAmount' => $session->getTotalAmount(),
                    'status' => $session->getStatus(),
                    'createdAt' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $sessions);

            return JsonResponse::success($sessionsArray, 'Sessions retrieved successfully');

        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving sessions');
        }
    }

    /**
     * GET /api/sessions/:id - Détails d'une session (user auth)
     */
    public function show(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis le middleware
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $sessionId = $request->getPathParam('id');

            if ($sessionId === null) {
                return JsonResponse::error('Session ID is required', null, 400);
            }

            // Récupère la session
            $session = $this->sessionRepository->findById($sessionId);

            if ($session === null) {
                return JsonResponse::notFound('Session not found');
            }

            // Vérifie que c'est bien la session de l'utilisateur
            if ($session->getUserId() !== $userId) {
                return JsonResponse::forbidden('Unauthorized: This is not your session');
            }

            // Retourne la session
            return JsonResponse::success([
                'id' => $session->getId(),
                'userId' => $session->getUserId(),
                'parkingId' => $session->getParkingId(),
                'reservationId' => $session->getReservationId(),
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $session->getEndTime()?->format('Y-m-d H:i:s'),
                'totalAmount' => $session->getTotalAmount(),
                'status' => $session->getStatus(),
                'createdAt' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
            ], 'Session retrieved successfully');

        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving session');
        }
    }
}
