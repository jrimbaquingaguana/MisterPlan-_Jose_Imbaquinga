<?php

declare(strict_types=1);

namespace App\Domain;

final class ReservaValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Datos de reserva no válidos');
    }
}
