<?php

declare(strict_types=1);

namespace App\Domain;

final class ReservaConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('El cambio no es compatible con el estado actual de la reserva.');
    }
}
