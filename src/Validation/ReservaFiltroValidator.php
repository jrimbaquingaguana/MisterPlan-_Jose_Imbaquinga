<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\EstadoReserva;

final class ReservaFiltroValidator
{
    public function validate(array $input): array
    {
        $errors = [];
        foreach ($input as $field => $value) {
            if (!in_array($field, ['estado', 'desde', 'hasta', 'q', 'page', 'per_page'], true)) {
                $errors[$field] = 'Parámetro desconocido.';
            } elseif (!is_string($value)) {
                $errors[$field] = 'Debe ser un texto.';
            }
        }
        if (isset($input['estado']) && !isset($errors['estado']) && $input['estado'] !== ''
            && EstadoReserva::tryFrom($input['estado']) === null) {
            $errors['estado'] = 'Estado no válido.';
        }
        foreach (['desde', 'hasta'] as $field) {
            if (isset($input[$field]) && !isset($errors[$field]) && $input[$field] !== ''
                && DateValidator::parse($input[$field]) === null) {
                $errors[$field] = 'Debe ser una fecha real YYYY-MM-DD entre los años 1000 y 9999.';
            }
        }
        if (!isset($errors['desde']) && !isset($errors['hasta'])
            && isset($input['desde'], $input['hasta']) && $input['desde'] !== '' && $input['hasta'] !== ''
            && $input['desde'] > $input['hasta']) {
            $errors['hasta'] = 'Hasta debe ser igual o posterior a desde.';
        }
        if (isset($input['q']) && !isset($errors['q'])) {
            if (!mb_check_encoding($input['q'], 'UTF-8')) {
                $errors['q'] = 'Debe ser un texto UTF-8 válido.';
            } elseif (mb_strlen(trim($input['q']), 'UTF-8') > 100) {
                $errors['q'] = 'La búsqueda admite como máximo 100 caracteres.';
            }
        }
        foreach (['page' => PHP_INT_MAX, 'per_page' => 100] as $field => $max) {
            if (array_key_exists($field, $input) && !isset($errors[$field]) && !$this->positiveInteger($input[$field], $max)) {
                $errors[$field] = 'Debe ser un entero positivo dentro del rango permitido.';
            }
        }
        if (!isset($errors['page']) && !isset($errors['per_page'])) {
            $page = (int) ($input['page'] ?? '1');
            $perPage = (int) ($input['per_page'] ?? '10');
            if ($page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
                $errors['page'] = 'La página excede el rango de paginación permitido.';
            }
        }
        return $errors;
    }

    private function positiveInteger(string $value, int $max): bool
    {
        if (preg_match('/\A[0-9]+\z/', $value) !== 1) {
            return false;
        }
        $digits = ltrim($value, '0');
        $limit = (string) $max;
        return $digits !== '' && (strlen($digits) < strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0));
    }
}
