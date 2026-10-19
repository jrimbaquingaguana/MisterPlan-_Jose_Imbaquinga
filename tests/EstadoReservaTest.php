<?php

declare(strict_types=1);

use App\Domain\EstadoReserva;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EstadoReservaTest extends TestCase
{
    #[DataProvider('transitions')]
    public function testTransitions(string $from, string $to, bool $allowed): void
    {
        self::assertSame($allowed, EstadoReserva::from($from)->canTransitionTo(EstadoReserva::from($to)));
    }

    public static function transitions(): iterable
    {
        yield ['pendiente', 'pendiente', false];
        yield ['pendiente', 'confirmada', true];
        yield ['pendiente', 'cancelada', true];
        yield ['confirmada', 'pendiente', false];
        yield ['confirmada', 'confirmada', false];
        yield ['confirmada', 'cancelada', true];
        yield ['cancelada', 'pendiente', false];
        yield ['cancelada', 'confirmada', false];
        yield ['cancelada', 'cancelada', false];
    }
}
