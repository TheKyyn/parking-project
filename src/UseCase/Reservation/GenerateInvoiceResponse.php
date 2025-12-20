<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * GenerateInvoiceResponse DTO
 */
class GenerateInvoiceResponse
{
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly string $reservationId,
        public readonly string $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $parkingId,
        public readonly string $parkingName,
        public readonly string $parkingAddress,
        public readonly string $reservationStart,
        public readonly string $reservationEnd,
        public readonly int $reservedDurationMinutes,
        public readonly int $actualDurationMinutes,
        public readonly float $hourlyRate,
        public readonly float $baseAmount,
        public readonly int $overstayMinutes,
        public readonly float $overstayAmount,
        public readonly float $penaltyAmount,
        public readonly float $totalAmount,
        public readonly string $status,
        public readonly string $generatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'invoice_number' => $this->invoiceNumber,
            'reservation_id' => $this->reservationId,
            'user' => [
                'id' => $this->userId,
                'name' => $this->userName,
                'email' => $this->userEmail
            ],
            'parking' => [
                'id' => $this->parkingId,
                'name' => $this->parkingName,
                'address' => $this->parkingAddress
            ],
            'reservation' => [
                'start' => $this->reservationStart,
                'end' => $this->reservationEnd,
                'reserved_duration_minutes' => $this->reservedDurationMinutes,
                'actual_duration_minutes' => $this->actualDurationMinutes
            ],
            'billing' => [
                'hourly_rate' => $this->hourlyRate,
                'base_amount' => $this->baseAmount,
                'overstay_minutes' => $this->overstayMinutes,
                'overstay_amount' => $this->overstayAmount,
                'penalty_amount' => $this->penaltyAmount,
                'total_amount' => $this->totalAmount
            ],
            'status' => $this->status,
            'generated_at' => $this->generatedAt
        ];
    }

    public function toHtml(): string
    {
        $formattedDate = date('d/m/Y H:i', strtotime($this->generatedAt));
        $startDate = date('d/m/Y H:i', strtotime($this->reservationStart));
        $endDate = date('d/m/Y H:i', strtotime($this->reservationEnd));

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {$this->invoiceNumber}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .invoice-header { border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
        .invoice-title { font-size: 28px; color: #2563eb; margin: 0; }
        .invoice-number { color: #666; font-size: 14px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: bold; color: #2563eb; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item { margin-bottom: 8px; }
        .info-label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; }
        .info-value { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: bold; color: #374151; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; background: #2563eb; color: white; }
        .total-row td { border: none; }
        .penalty { color: #dc2626; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h1 class="invoice-title">FACTURE</h1>
        <p class="invoice-number">N° {$this->invoiceNumber}</p>
        <p class="invoice-number">Date: {$formattedDate}</p>
    </div>

    <div class="info-grid">
        <div class="section">
            <div class="section-title">Client</div>
            <div class="info-item">
                <div class="info-label">Nom</div>
                <div class="info-value">{$this->userName}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value">{$this->userEmail}</div>
            </div>
        </div>
        <div class="section">
            <div class="section-title">Parking</div>
            <div class="info-item">
                <div class="info-label">Nom</div>
                <div class="info-value">{$this->parkingName}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Adresse</div>
                <div class="info-value">{$this->parkingAddress}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Détails de la réservation</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Début</div>
                <div class="info-value">{$startDate}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Fin</div>
                <div class="info-value">{$endDate}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Durée réservée</div>
                <div class="info-value">{$this->reservedDurationMinutes} minutes</div>
            </div>
            <div class="info-item">
                <div class="info-label">Durée effective</div>
                <div class="info-value">{$this->actualDurationMinutes} minutes</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Facturation</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Stationnement ({$this->reservedDurationMinutes} min @ {$this->hourlyRate}€/h)</td>
                    <td class="amount">{$this->baseAmount} €</td>
                </tr>
HTML;

        if ($this->overstayMinutes > 0) {
            $html = $html . <<<HTML
                <tr class="penalty">
                    <td>Dépassement ({$this->overstayMinutes} min)</td>
                    <td class="amount">{$this->overstayAmount} €</td>
                </tr>
                <tr class="penalty">
                    <td>Pénalité de dépassement</td>
                    <td class="amount">{$this->penaltyAmount} €</td>
                </tr>
HTML;
        }

        $html = $html . <<<HTML
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="amount">{$this->totalAmount} €</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Merci pour votre confiance - Parking Partagé System</p>
        <p>Cette facture a été générée automatiquement</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
