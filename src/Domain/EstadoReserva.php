<?php

declare(strict_types=1);

namespace App\Domain;

enum EstadoReserva: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';

    public function canTransitionTo(self $estado): bool
    {
        return match ($this) {
            self::Pendiente => $estado === self::Confirmada || $estado === self::Cancelada,
            self::Confirmada => $estado === self::Cancelada,
            self::Cancelada => false,
        };
    }
}
