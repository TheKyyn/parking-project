<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwner;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwnerRequest;
use ParkingSystem\UseCase\ParkingOwner\AuthenticateParkingOwner;
use ParkingSystem\UseCase\ParkingOwner\AuthenticateParkingOwnerRequest;
use ParkingSystem\UseCase\ParkingOwner\GetParkingOwnerProfile;
use ParkingSystem\UseCase\ParkingOwner\UpdateParkingOwner;
use ParkingSystem\UseCase\ParkingOwner\UpdateParkingOwnerRequest;
use ParkingSystem\UseCase\ParkingOwner\OwnerAlreadyExistsException;
use ParkingSystem\UseCase\ParkingOwner\InvalidOwnerCredentialsException;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;

/**
 * Controller pour les endpoints Owner
 */
class OwnerController
{
    public function __construct(
        private CreateParkingOwner $createOwnerUseCase,
        private AuthenticateParkingOwner $authenticateOwnerUseCase,
        private GetParkingOwnerProfile $getOwnerProfileUseCase,
        private UpdateParkingOwner $updateOwnerUseCase,
        private ParkingOwnerRepositoryInterface $ownerRepository
    ) {
    }

    /**
     * POST /api/owners - Créer un owner (public)
     */
    public function register(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère le body
            $body = $request->getBody();

            if ($body === null) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            // Validation
            $validator = new SimpleValidator();
            $errors = $validator->validate($body, [
                'email' => ['required', 'email'],
                'password' => ['required', 'min:8'],
                'firstName' => ['required', 'min:2'],
                'lastName' => ['required', 'min:2'],
            ]);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Crée le request DTO
            $useCaseRequest = new CreateParkingOwnerRequest(
                $body['email'],
                $body['password'],
                $body['firstName'],
                $body['lastName']
            );

            // Execute le use case
            $response = $this->createOwnerUseCase->execute($useCaseRequest);

            // Récupère l'owner pour avoir firstName et lastName séparés
            $owner = $this->ownerRepository->findById($response->ownerId);

            // Retourne la réponse
            return JsonResponse::created(
                [
                    'ownerId' => $response->ownerId,
                    'email' => $response->email,
                    'firstName' => $owner->getFirstName(),
                    'lastName' => $owner->getLastName(),
                    'createdAt' => $owner->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
                'Owner created successfully'
            );

        } catch (OwnerAlreadyExistsException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while creating owner');
        }
    }

    /**
     * POST /api/owners/login - Authentifier un owner (public)
     */
    public function login(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère le body
            $body = $request->getBody();

            if ($body === null) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            // Validation
            $validator = new SimpleValidator();
            $errors = $validator->validate($body, [
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Crée le request DTO
            $useCaseRequest = new AuthenticateParkingOwnerRequest(
                $body['email'],
                $body['password']
            );

            // Execute le use case
            $response = $this->authenticateOwnerUseCase->execute($useCaseRequest);

            // Récupère l'owner pour avoir firstName et lastName séparés
            $owner = $this->ownerRepository->findById($response->ownerId);

            // Retourne la réponse
            return JsonResponse::success(
                [
                    'token' => $response->token,
                    'ownerId' => $response->ownerId,
                    'email' => $response->email,
                    'firstName' => $owner->getFirstName(),
                    'lastName' => $owner->getLastName(),
                    'expiresIn' => $response->expiresIn,
                ],
                'Authentication successful'
            );

        } catch (InvalidOwnerCredentialsException $e) {
            return JsonResponse::unauthorized($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred during authentication');
        }
    }

    /**
     * GET /api/owners/profile - Récupérer le profil owner (owner auth required)
     */
    public function getProfile(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'ownerId depuis le middleware
            $ownerId = $request->getPathParam('_ownerId');

            if ($ownerId === null) {
                return JsonResponse::unauthorized('Owner authentication required');
            }

            // Execute le use case
            $response = $this->getOwnerProfileUseCase->execute($ownerId);

            // Récupère l'owner pour avoir firstName et lastName séparés
            $owner = $this->ownerRepository->findById($response->ownerId);

            // Retourne la réponse
            return JsonResponse::success(
                [
                    'ownerId' => $response->ownerId,
                    'email' => $response->email,
                    'firstName' => $owner->getFirstName(),
                    'lastName' => $owner->getLastName(),
                    'createdAt' => $owner->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
                'Owner profile retrieved successfully'
            );

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving owner profile');
        }
    }

    /**
     * PUT /api/owners/profile - Mettre à jour le profil owner (owner auth required)
     */
    public function updateProfile(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'ownerId depuis le middleware
            $ownerId = $request->getPathParam('_ownerId');

            if ($ownerId === null) {
                return JsonResponse::unauthorized('Owner authentication required');
            }

            // Récupère le body
            $body = $request->getBody();

            if ($body === null) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            // Validation (tous les champs optionnels)
            $validator = new SimpleValidator();
            $rules = [];

            if (isset($body['firstName'])) {
                $rules['firstName'] = ['min:2'];
            }
            if (isset($body['lastName'])) {
                $rules['lastName'] = ['min:2'];
            }

            $errors = $validator->validate($body, $rules);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            // Récupère l'owner actuel pour garder les valeurs non modifiées
            $owner = $this->ownerRepository->findById($ownerId);

            if ($owner === null) {
                return JsonResponse::notFound('Owner not found');
            }

            // Utilise les valeurs actuelles si non fournies
            $firstName = $body['firstName'] ?? $owner->getFirstName();
            $lastName = $body['lastName'] ?? $owner->getLastName();

            // Crée le request DTO
            $useCaseRequest = new UpdateParkingOwnerRequest(
                $ownerId,
                $firstName,
                $lastName
            );

            // Execute le use case
            $response = $this->updateOwnerUseCase->execute($useCaseRequest);

            // Récupère l'owner mis à jour pour avoir firstName et lastName séparés
            $updatedOwner = $this->ownerRepository->findById($response->ownerId);

            // Retourne la réponse
            return JsonResponse::success(
                [
                    'ownerId' => $response->ownerId,
                    'email' => $response->email,
                    'firstName' => $updatedOwner->getFirstName(),
                    'lastName' => $updatedOwner->getLastName(),
                ],
                'Owner profile updated successfully'
            );

        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while updating owner profile');
        }
    }
}
