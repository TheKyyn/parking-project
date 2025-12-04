<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Validation;

/**
 * Interface pour valider des données
 */
interface ValidatorInterface
{
    /**
     * Valide des données selon des règles
     *
     * @param array<string, mixed> $data Données à valider
     * @param array<string, array<string>> $rules Règles de validation
     * @return array<string, array<string>> Erreurs de validation (vide si valide)
     */
    public function validate(array $data, array $rules): array;

    /**
     * Vérifie si la validation a des erreurs
     */
    public function hasErrors(): bool;

    /**
     * Récupère toutes les erreurs
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array;
}
