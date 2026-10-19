<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\ReservaNotFoundException;

final class ReservaIdValidator
{
    public static function parse(string $id): int
    {
        $digits = ltrim($id, '0');
        if (preg_match('/\A[0-9]+\z/', $id) !== 1 || $digits === '' || strlen($digits) > 10
            || (strlen($digits) === 10 && strcmp($digits, '4294967295') > 0)) {
            throw new ReservaNotFoundException();
        }
        return (int) $digits;
    }
}
