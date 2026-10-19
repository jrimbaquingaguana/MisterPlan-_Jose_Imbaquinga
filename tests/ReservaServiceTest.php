<?php

declare(strict_types=1);

use App\Domain\ReservaValidationException;
use App\Repository\ReservaEventoRepository;
use App\Repository\ReservaRepository;
use App\Service\ReservaService;
use PHPUnit\Framework\TestCase;

final class ReservaServiceTest extends TestCase
{
    public function testInvalidCreationDoesNotTouchPdo(): void
    {
        $pdo = new class extends PDO {
            public function __construct() {}
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new LogicException('No debe consultarse el repositorio.');
            }
            public function beginTransaction(): bool
            {
                throw new LogicException('No debe iniciarse la transacción.');
            }
        };
        $service = new ReservaService(new ReservaRepository($pdo), new ReservaEventoRepository($pdo));
        $this->expectException(ReservaValidationException::class);
        $service->create([]);
    }
}
