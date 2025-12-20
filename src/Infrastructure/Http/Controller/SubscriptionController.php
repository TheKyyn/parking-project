<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Controller;

use ParkingSystem\Infrastructure\Http\Request\HttpRequestInterface;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use ParkingSystem\UseCase\Subscription\CreateSubscription;
use ParkingSystem\UseCase\Subscription\CreateSubscriptionRequest;
use ParkingSystem\UseCase\Subscription\GetParkingSubscriptions;
use ParkingSystem\UseCase\Subscription\GetParkingSubscriptionsRequest;
use ParkingSystem\UseCase\Subscription\ParkingNotFoundException;
use ParkingSystem\UseCase\Subscription\UserNotFoundException;
use ParkingSystem\UseCase\Subscription\SlotConflictException;
use ParkingSystem\UseCase\Subscription\InvalidDurationException;
use ParkingSystem\UseCase\Subscription\InvalidTimeSlotException;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

class SubscriptionController
{
    public function __construct(
        private CreateSubscription $createSubscriptionUseCase,
        private GetParkingSubscriptions $getParkingSubscriptionsUseCase,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function create(HttpRequestInterface $request): JsonResponse
    {
        try {
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $body = $request->getBody();

            if ($body === null) {
                return JsonResponse::error('Request body is required', null, 400);
            }

            $validator = new SimpleValidator();
            $errors = $validator->validate($body, [
                'parkingId' => ['required'],
                'durationMonths' => ['required', 'numeric'],
                'startDate' => ['required'],
                'weeklyTimeSlots' => ['required'],
            ]);

            if ($validator->hasErrors()) {
                return JsonResponse::validationError($errors);
            }

            $startDate = new \DateTimeImmutable($body['startDate']);

            $useCaseRequest = new CreateSubscriptionRequest(
                $userId,
                $body['parkingId'],
                $body['weeklyTimeSlots'],
                (int)$body['durationMonths'],
                $startDate
            );

            $response = $this->createSubscriptionUseCase->execute($useCaseRequest);

            return JsonResponse::created([
                'subscriptionId' => $response->subscriptionId,
                'userId' => $response->userId,
                'parkingId' => $response->parkingId,
                'weeklyTimeSlots' => $response->weeklyTimeSlots,
                'durationMonths' => $response->durationMonths,
                'startDate' => $response->startDate,
                'endDate' => $response->endDate,
                'monthlyAmount' => $response->monthlyAmount,
                'totalAmount' => $response->totalAmount,
                'status' => $response->status
            ], 'Subscription created successfully');

        } catch (ParkingNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (UserNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (SlotConflictException $e) {
            return JsonResponse::error($e->getMessage(), null, 409);
        } catch (InvalidDurationException | InvalidTimeSlotException $e) {
            return JsonResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponse::serverError('Error creating subscription: ' . $e->getMessage());
        }
    }

    public function listByParking(HttpRequestInterface $request): JsonResponse
    {
        try {
            $parkingId = $request->getPathParam('id');

            if ($parkingId === null) {
                return JsonResponse::error('Parking ID is required', null, 400);
            }

            $query = $request->getQueryParams();
            $activeOnly = isset($query['active']) && $query['active'] === 'true';

            $useCaseRequest = new GetParkingSubscriptionsRequest($parkingId, $activeOnly);
            $response = $this->getParkingSubscriptionsUseCase->execute($useCaseRequest);

            $subscriptionsArray = array_map(fn($s) => $s->toArray(), $response->subscriptions);

            return JsonResponse::success([
                'parkingId' => $response->parkingId,
                'totalCount' => $response->totalCount,
                'subscriptions' => $subscriptionsArray
            ], 'Subscriptions retrieved successfully');

        } catch (ParkingNotFoundException $e) {
            return JsonResponse::notFound($e->getMessage());
        } catch (\Exception $e) {
            return JsonResponse::serverError('Error retrieving subscriptions');
        }
    }

    public function index(HttpRequestInterface $request): JsonResponse
    {
        try {
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $subscriptions = $this->subscriptionRepository->findByUserId($userId);

            $subscriptionsArray = array_map(function ($subscription) {
                return [
                    'id' => $subscription->getId(),
                    'userId' => $subscription->getUserId(),
                    'parkingId' => $subscription->getParkingId(),
                    'weeklyTimeSlots' => $subscription->getWeeklyTimeSlots(),
                    'durationMonths' => $subscription->getDurationMonths(),
                    'startDate' => $subscription->getStartDate()->format('Y-m-d'),
                    'endDate' => $subscription->getEndDate()->format('Y-m-d'),
                    'monthlyAmount' => $subscription->getMonthlyAmount(),
                    'totalAmount' => $subscription->getTotalAmount(),
                    'status' => $subscription->getStatus(),
                    'remainingDays' => $subscription->getRemainingDays()
                ];
            }, $subscriptions);

            return JsonResponse::success($subscriptionsArray, 'Subscriptions retrieved');

        } catch (\Exception $e) {
            return JsonResponse::serverError('Error retrieving subscriptions');
        }
    }

    public function show(HttpRequestInterface $request): JsonResponse
    {
        try {
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $subscriptionId = $request->getPathParam('id');

            if ($subscriptionId === null) {
                return JsonResponse::error('Subscription ID is required', null, 400);
            }

            $subscription = $this->subscriptionRepository->findById($subscriptionId);

            if ($subscription === null) {
                return JsonResponse::notFound('Subscription not found');
            }

            if ($subscription->getUserId() !== $userId) {
                return JsonResponse::forbidden('Unauthorized access');
            }

            return JsonResponse::success([
                'id' => $subscription->getId(),
                'userId' => $subscription->getUserId(),
                'parkingId' => $subscription->getParkingId(),
                'weeklyTimeSlots' => $subscription->getWeeklyTimeSlots(),
                'durationMonths' => $subscription->getDurationMonths(),
                'startDate' => $subscription->getStartDate()->format('Y-m-d'),
                'endDate' => $subscription->getEndDate()->format('Y-m-d'),
                'monthlyAmount' => $subscription->getMonthlyAmount(),
                'totalAmount' => $subscription->getTotalAmount(),
                'status' => $subscription->getStatus(),
                'remainingDays' => $subscription->getRemainingDays()
            ], 'Subscription retrieved');

        } catch (\Exception $e) {
            return JsonResponse::serverError('Error retrieving subscription');
        }
    }

    public function cancel(HttpRequestInterface $request): JsonResponse
    {
        try {
            $userId = $request->getPathParam('_userId');

            if ($userId === null) {
                return JsonResponse::unauthorized('User authentication required');
            }

            $subscriptionId = $request->getPathParam('id');

            if ($subscriptionId === null) {
                return JsonResponse::error('Subscription ID is required', null, 400);
            }

            $subscription = $this->subscriptionRepository->findById($subscriptionId);

            if ($subscription === null) {
                return JsonResponse::notFound('Subscription not found');
            }

            if ($subscription->getUserId() !== $userId) {
                return JsonResponse::forbidden('Unauthorized access');
            }

            if (!$subscription->isActive()) {
                return JsonResponse::error('Subscription is not active', null, 400);
            }

            $subscription->cancel();
            $this->subscriptionRepository->save($subscription);

            return JsonResponse::success(['status' => 'cancelled'], 'Subscription cancelled');

        } catch (\Exception $e) {
            return JsonResponse::serverError('Error cancelling subscription');
        }
    }
}
