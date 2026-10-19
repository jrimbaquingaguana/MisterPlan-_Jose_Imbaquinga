<?php

declare(strict_types=1);

namespace App\Validation;

final class ReservaValidator
{
    public function validateCreate(array $input): array
    {
        $errors = [];
        $required = ['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'fecha_entrada', 'fecha_salida', 'importe'];
        foreach ($input as $field => $value) {
            if (in_array($field, ['id', 'estado', 'fecha_creacion'], true)) {
                $errors[$field] = 'Este campo lo controla el servidor.';
            } elseif (!in_array($field, [...$required, 'notas'], true)) {
                $errors[$field] = 'Campo desconocido.';
            }
        }
        foreach ($required as $field) {
            if (!array_key_exists($field, $input)) {
                $errors[$field] = 'Este campo es obligatorio.';
            }
        }
        foreach (['nombre_huesped' => 150, 'email_huesped' => 254, 'nombre_alojamiento' => 150] as $field => $limit) {
            if (array_key_exists($field, $input)) {
                $error = $this->validateText($input[$field], $limit, true);
                if ($error === null && $field === 'email_huesped'
                    && filter_var(trim($input[$field]), FILTER_VALIDATE_EMAIL) === false) {
                    $error = 'El email no tiene un formato válido.';
                }
                if ($error !== null) {
                    $errors[$field] = $error;
                }
            }
        }
        $dates = [];
        foreach (['fecha_entrada', 'fecha_salida'] as $field) {
            if (array_key_exists($field, $input)) {
                $dates[$field] = DateValidator::parse($input[$field]);
                if ($dates[$field] === null) {
                    $errors[$field] = 'Debe ser una fecha real con formato YYYY-MM-DD y año entre 1000 y 9999.';
                }
            }
        }
        if (isset($dates['fecha_entrada'], $dates['fecha_salida'])
            && $dates['fecha_salida'] <= $dates['fecha_entrada']) {
            $errors['fecha_salida'] = 'La salida debe ser posterior a la entrada.';
        }
        if (array_key_exists('importe', $input) && !$this->validAmount($input['importe'])) {
            $errors['importe'] = 'Debe ser un importe entre 0 y 99999999.99, con punto y como máximo dos decimales.';
        }
        if (array_key_exists('notas', $input) && $input['notas'] !== null) {
            $error = $this->validateText($input['notas'], 5000, false);
            if ($error !== null) {
                $errors['notas'] = $error;
            }
        }
        return $errors;
    }

    private function validateText(mixed $value, int $limit, bool $singleLine): ?string
    {
        if (!is_string($value)) {
            return 'Debe ser un texto.';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            return 'Debe ser un texto UTF-8 válido.';
        }
        $controls = $singleLine ? '/\p{Cc}/u' : '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u';
        if (preg_match($controls, $value) === 1) {
            return 'El texto contiene caracteres de control no permitidos.';
        }
        if ($singleLine && trim($value) === '') {
            return 'Este campo es obligatorio.';
        }
        if (mb_strlen($value, 'UTF-8') > $limit) {
            return 'El texto supera el máximo de ' . $limit . ' caracteres.';
        }
        return null;
    }

    private function validAmount(mixed $value): bool
    {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }
        $value = (string) $value;
        if (preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $value) !== 1) {
            return false;
        }
        $integer = ltrim(explode('.', $value, 2)[0], '0');
        return strlen($integer) <= 8;
    }
}
