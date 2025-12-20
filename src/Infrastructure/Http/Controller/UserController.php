<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\User\CreateUser;
use ParkingSystem\UseCase\User\CreateUserRequest;
use ParkingSystem\UseCase\User\AuthenticateUser;
use ParkingSystem\UseCase\User\AuthenticateUserRequest;
use ParkingSystem\UseCase\User\UserAlreadyExistsException;
use ParkingSystem\UseCase\User\InvalidCredentialsException;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * Controller pour les endpoints User
 */
class UserController
{
    public function __construct(
        private CreateUser $createUserUseCase,
        private AuthenticateUser $authenticateUserUseCase,
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * POST /api/users - Créer un nouvel utilisateur
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
            $useCaseRequest = new CreateUserRequest(
                $body['email'],
                $body['password'],
                $body['firstName'],
                $body['lastName']
            );

            // Execute le use case
            $response = $this->createUserUseCase->execute($useCaseRequest);

            // Retourne la réponse
            return JsonResponse::created([
                'userId' => $response->userId,
                'email' => $response->email,
                'fullName' => $response->fullName,
                'createdAt' => $response->createdAt,
            ], 'User created successfully');

        } catch (UserAlreadyExistsException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while creating user');
        }
    }

    /**
     * POST /api/auth/login - Authentifier un utilisateur
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
            $useCaseRequest = new AuthenticateUserRequest(
                $body['email'],
                $body['password']
            );

            // Execute le use case
            $response = $this->authenticateUserUseCase->execute($useCaseRequest);

            // Retourne la réponse
            return JsonResponse::success([
                'userId' => $response->userId,
                'email' => $response->email,
                'fullName' => $response->fullName,
                'token' => $response->token,
                'expiresIn' => $response->expiresIn,
            ], 'Authentication successful');

        } catch (InvalidCredentialsException $e) {
            return JsonResponse::unauthorized($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred during authentication');
        }
    }

    /**
     * GET /api/users/profile - Récupérer le profil de l'utilisateur authentifié
     *
     * Nécessite le middleware d'authentification
     */
    public function getProfile(HttpRequestInterface $request): JsonResponse
    {
        try {
            // Récupère l'userId depuis les pathParams (injecté par AuthMiddleware)
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('Authentication required');
            }

            // Récupère l'utilisateur
            $user = $this->userRepository->findById($userId);

            if ($user === null) {
                return JsonResponse::notFound('User not found');
            }

            // Retourne le profil
            return JsonResponse::success([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ], 'Profile retrieved successfully');

        } catch (\Exception $e) {
            return JsonResponse::serverError('An error occurred while retrieving profile');
        }
    }
}
