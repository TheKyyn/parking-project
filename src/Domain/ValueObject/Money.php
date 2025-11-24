<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\ValueObject;

/**
 * Money Value Object
 * Domain Layer - Immutable value object with NO external dependencies
 */
class Money
{
    private int $amountInCents;
    private string $currency;

    public function __construct(float $amount, string $currency = 'EUR')
    {
        $this->validateAmount($amount);
        $this->validateCurrency($currency);
        
        $this->amountInCents = (int)round($amount * 100);
        $this->currency = strtoupper($currency);
    }

    public function getAmount(): float
    {
        return $this->amountInCents / 100;
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): Money
    {
        $this->ensureSameCurrency($other);
        
        return new self(
            ($this->amountInCents + $other->amountInCents) / 100,
            $this->currency
        );
    }

    public function subtract(Money $other): Money
    {
        $this->ensureSameCurrency($other);
        
        $newAmount = ($this->amountInCents - $other->amountInCents) / 100;
        if ($newAmount < 0) {
            throw new \InvalidArgumentException('Cannot subtract to negative amount');
        }
        
        return new self($newAmount, $this->currency);
    }

    public function multiply(float $multiplier): Money
    {
        if ($multiplier < 0) {
            throw new \InvalidArgumentException('Multiplier cannot be negative');
        }
        
        return new self($this->getAmount() * $multiplier, $this->currency);
    }

    public function divide(float $divisor): Money
    {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException('Divisor must be positive');
        }
        
        return new self($this->getAmount() / $divisor, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents &&
               $this->currency === $other->currency;
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents >= $other->amountInCents;
    }

    public function isLessThan(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents < $other->amountInCents;
    }

    public function isLessThanOrEqual(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents <= $other->amountInCents;
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountInCents > 0;
    }

    public function toString(): string
    {
        return sprintf('%.2f %s', $this->getAmount(), $this->currency);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function format(): string
    {
        switch ($this->currency) {
            case 'EUR':
                return sprintf('€%.2f', $this->getAmount());
            case 'USD':
                return sprintf('$%.2f', $this->getAmount());
            case 'GBP':
                return sprintf('£%.2f', $this->getAmount());
            default:
                return sprintf('%.2f %s', $this->getAmount(), $this->currency);
        }
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->getAmount(),
            'currency' => $this->currency,
            'amountInCents' => $this->amountInCents
        ];
    }

    public static function zero(string $currency = 'EUR'): self
    {
        return new self(0.0, $currency);
    }

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents / 100, $currency);
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['amount'], $data['currency'])) {
            throw new \InvalidArgumentException('Array must contain amount and currency');
        }
        
        return new self((float)$data['amount'], $data['currency']);
    }

    private function validateAmount(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        
        if (!is_finite($amount)) {
            throw new \InvalidArgumentException('Amount must be a finite number');
        }
    }

    private function validateCurrency(string $currency): void
    {
        if (empty(trim($currency))) {
            throw new \InvalidArgumentException('Currency cannot be empty');
        }
        
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be 3 characters long');
        }
        
        if (!ctype_alpha($currency)) {
            throw new \InvalidArgumentException('Currency must contain only letters');
        }
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                sprintf('Cannot perform operation with different currencies: %s and %s', 
                    $this->currency, 
                    $other->currency
                )
            );
        }
    }
}