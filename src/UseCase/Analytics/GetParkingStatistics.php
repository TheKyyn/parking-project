<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Analytics;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * GetParkingStatistics Use Case
 * Use Case Layer - Retrieves comprehensive statistics for a parking
 */
class GetParkingStatistics
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private ParkingSessionRepositoryInterface $sessionRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function execute(
        GetParkingStatisticsRequest $request
    ): GetParkingStatisticsResponse {
        $this->validateRequest($request);

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        // Determine date range
        $toDate = $request->toDate ?? new \DateTimeImmutable();
        $fromDate = $request->fromDate ?? $toDate->modify('-30 days');

        $fromDateImmutable = \DateTimeImmutable::createFromInterface($fromDate);
        $toDateImmutable = \DateTimeImmutable::createFromInterface($toDate);

        // Get reservation statistics
        $reservationStats = $this->getReservationStatistics(
            $request->parkingId,
            $fromDateImmutable,
            $toDateImmutable
        );

        // Get session statistics
        $sessionStats = $this->getSessionStatistics(
            $request->parkingId,
            $fromDateImmutable,
            $toDateImmutable
        );

        // Get subscription statistics
        $subscriptionStats = $this->getSubscriptionStatistics(
            $request->parkingId,
            $toDateImmutable
        );

        // Calculate current occupancy
        $activeSessions = $this->sessionRepository->findActiveSessionsForParking(
            $request->parkingId
        );
        $currentlyOccupied = count($activeSessions);
        $occupancyRate = $parking->getTotalSpaces() > 0
            ? ($currentlyOccupied / $parking->getTotalSpaces()) * 100
            : 0;

        // Calculate revenue
        $totalRevenue = $reservationStats['revenue'] +
                        $sessionStats['revenue'] +
                        $subscriptionStats['revenue'];

        $daysDiff = max(1, $fromDateImmutable->diff($toDateImmutable)->days);
        $averageDailyRevenue = $totalRevenue / $daysDiff;

        // Calculate peak hours and occupancy by day
        $peakHours = $this->calculatePeakHours($sessionStats['sessions']);
        $occupancyByDay = $this->calculateOccupancyByDayOfWeek($sessionStats['sessions']);

        return new GetParkingStatisticsResponse(
            $request->parkingId,
            $fromDateImmutable->format(\DateTimeInterface::ATOM),
            $toDateImmutable->format(\DateTimeInterface::ATOM),
            $parking->getTotalSpaces(),
            $currentlyOccupied,
            round($occupancyRate, 2),
            $reservationStats['total'],
            $reservationStats['completed'],
            $reservationStats['cancelled'],
            $sessionStats['total'],
            $sessionStats['overstayed'],
            $sessionStats['averageDuration'],
            $subscriptionStats['active'],
            round($totalRevenue, 2),
            round($averageDailyRevenue, 2),
            $peakHours,
            $occupancyByDay
        );
    }

    private function validateRequest(GetParkingStatisticsRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }

        if ($request->fromDate !== null && $request->toDate !== null) {
            if ($request->fromDate > $request->toDate) {
                throw new \InvalidArgumentException(
                    'From date must be before to date'
                );
            }
        }
    }

    private function getReservationStatistics(
        string $parkingId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        $reservations = $this->reservationRepository->findByParkingId($parkingId);

        $total = 0;
        $completed = 0;
        $cancelled = 0;
        $revenue = 0.0;

        foreach ($reservations as $reservation) {
            $start = $reservation->getStartTime();
            if ($start < $from || $start > $to) {
                continue;
            }

            $total++;

            switch ($reservation->getStatus()) {
                case 'completed':
                    $completed++;
                    $revenue += $reservation->getTotalAmount();
                    break;
                case 'confirmed':
                    $revenue += $reservation->getTotalAmount();
                    break;
                case 'cancelled':
                    $cancelled++;
                    break;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'revenue' => $revenue
        ];
    }

    private function getSessionStatistics(
        string $parkingId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        $allSessions = $this->sessionRepository->findByParkingId($parkingId);

        $sessions = [];
        $total = 0;
        $overstayed = 0;
        $revenue = 0.0;
        $totalDuration = 0;

        foreach ($allSessions as $session) {
            $start = $session->getStartTime();
            if ($start < $from || $start > $to) {
                continue;
            }

            $sessions[] = $session;
            $total++;

            if ($session->isOverstayed()) {
                $overstayed++;
            }

            if ($session->getTotalAmount() !== null) {
                $revenue += $session->getTotalAmount();
            }

            $duration = $session->getDurationInMinutes();
            if ($duration !== null) {
                $totalDuration += $duration;
            }
        }

        $averageDuration = $total > 0 ? $totalDuration / $total : 0;

        return [
            'sessions' => $sessions,
            'total' => $total,
            'overstayed' => $overstayed,
            'revenue' => $revenue,
            'averageDuration' => round($averageDuration, 2)
        ];
    }

    private function getSubscriptionStatistics(
        string $parkingId,
        \DateTimeImmutable $currentDate
    ): array {
        $subscriptions = $this->subscriptionRepository->findByParkingId($parkingId);

        $active = 0;
        $revenue = 0.0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->isActive() && $subscription->isValidAt($currentDate)) {
                $active++;
                $revenue += $subscription->getMonthlyAmount();
            }
        }

        return [
            'active' => $active,
            'revenue' => $revenue
        ];
    }

    private function calculatePeakHours(array $sessions): array
    {
        $hourCounts = array_fill(0, 24, 0);

        foreach ($sessions as $session) {
            $hour = (int)$session->getStartTime()->format('G');
            $hourCounts[$hour]++;
        }

        // Find top 3 peak hours
        arsort($hourCounts);
        $peakHours = [];

        $count = 0;
        foreach ($hourCounts as $hour => $sessionCount) {
            if ($count >= 3 || $sessionCount === 0) {
                break;
            }

            $peakHours[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00-%02d:00', $hour, $hour + 1),
                'sessionCount' => $sessionCount
            ];
            $count++;
        }

        return $peakHours;
    }

    private function calculateOccupancyByDayOfWeek(array $sessions): array
    {
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayCounts = array_fill(0, 7, 0);

        foreach ($sessions as $session) {
            $dayOfWeek = (int)$session->getStartTime()->format('w');
            $dayCounts[$dayOfWeek]++;
        }

        $result = [];
        foreach ($dayCounts as $day => $count) {
            $result[] = [
                'day' => $day,
                'name' => $dayNames[$day],
                'sessionCount' => $count
            ];
        }

        return $result;
    }
}
