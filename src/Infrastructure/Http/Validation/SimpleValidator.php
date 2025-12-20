<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Validation;

/**
 * Validateur simple de données
 *
 * Règles supportées:
 * - required: Champ obligatoire
 * - email: Format email valide
 * - string: Doit être une chaîne
 * - integer: Doit être un entier
 * - numeric: Doit être numérique
 * - min:X: Longueur/valeur minimum
 * - max:X: Longueur/valeur maximum
 * - between:X,Y: Entre X et Y
 * - in:X,Y,Z: Dans la liste de valeurs
 */
class SimpleValidator implements ValidatorInterface
{
    /** @var array<string, array<string>> */
    private array $errors = [];

    /**
     * {@inheritDoc}
     */
    public function validate(array $data, array $rules): array
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->validateRule($field, $value, $rule);
            }
        }

        return $this->errors;
    }

    /**
     * {@inheritDoc}
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Valide une règle spécifique
     */
    private function validateRule(string $field, mixed $value, string $rule): void
    {
        // Parse rule (ex: "min:5" => rule="min", param="5")
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        $method = 'validate' . ucfirst($ruleName);

        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("Unknown validation rule: {$ruleName}");
        }

        $result = $this->$method($value, $param);

        if ($result !== true) {
            $this->addError($field, $result);
        }
    }

    /**
     * Ajoute une erreur
     */
    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Règle: required
     */
    private function validateRequired(mixed $value, ?string $param): string|bool
    {
        if ($value === null || $value === '' || $value === []) {
            return 'This field is required';
        }

        return true;
    }

    /**
     * Règle: email
     */
    private function validateEmail(mixed $value, ?string $param): string|bool
    {
        if ($value === null || $value === '') {
            return true; // Skip if empty (use 'required' separately)
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'This field must be a valid email address';
        }

        return true;
    }

    /**
     * Règle: string
     */
    private function validateString(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return 'This field must be a string';
        }

        return true;
    }

    /**
     * Règle: integer
     */
    private function validateInteger(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_int($value) && !ctype_digit((string)$value)) {
            return 'This field must be an integer';
        }

        return true;
    }

    /**
     * Règle: numeric
     */
    private function validateNumeric(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_numeric($value)) {
            return 'This field must be numeric';
        }

        return true;
    }

    /**
     * Règle: min:X
     */
    private function validateMin(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if ($param === null) {
            throw new \InvalidArgumentException('Min rule requires a parameter');
        }

        $min = (int)$param;

        if (is_string($value) && strlen($value) < $min) {
            return "This field must be at least {$min} characters";
        }

        if (is_numeric($value) && $value < $min) {
            return "This field must be at least {$min}";
        }

        return true;
    }

    /**
     * Règle: max:X
     */
    private function validateMax(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if ($param === null) {
            throw new \InvalidArgumentException('Max rule requires a parameter');
        }

        $max = (int)$param;

        if (is_string($value) && strlen($value) > $max) {
            return "This field must not exceed {$max} characters";
        }

        if (is_numeric($value) && $value > $max) {
            return "This field must not exceed {$max}";
        }

        return true;
    }

    /**
     * Règle: between:X,Y
     */
    private function validateBetween(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if ($param === null) {
            throw new \InvalidArgumentException('Between rule requires parameters');
        }

        $parts = explode(',', $param);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Between rule requires two parameters (min,max)');
        }

        $min = (int)$parts[0];
        $max = (int)$parts[1];

        if (is_string($value)) {
            $length = strlen($value);
            if ($length < $min || $length > $max) {
                return "This field must be between {$min} and {$max} characters";
            }
        }

        if (is_numeric($value)) {
            if ($value < $min || $value > $max) {
                return "This field must be between {$min} and {$max}";
            }
        }

        return true;
    }

    /**
     * Règle: in:X,Y,Z
     */
    private function validateIn(mixed $value, ?string $param): string|bool
    {
        if ($value === null) {
            return true;
        }

        if ($param === null) {
            throw new \InvalidArgumentException('In rule requires parameters');
        }

        $allowedValues = explode(',', $param);

        if (!in_array($value, $allowedValues, true)) {
            $valuesList = implode(', ', $allowedValues);
            return "This field must be one of: {$valuesList}";
        }

        return true;
    }
}
