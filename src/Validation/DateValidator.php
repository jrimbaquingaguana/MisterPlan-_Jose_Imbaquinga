<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;

final class DateValidator
{
    public static function parse(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return null;
        }
        return $date;
    }
}
