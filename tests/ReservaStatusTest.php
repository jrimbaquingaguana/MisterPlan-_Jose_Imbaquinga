<?php

declare(strict_types=1);

use App\Controller\ReservaApiController;
use App\Domain\ReservaNotFoundException;
use App\Domain\ReservaValidationException;
use App\Http\Router;
use App\Validation\ReservaFiltroValidator;
use App\Validation\ReservaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservaStatusTest extends TestCase
{
    public static function invalidBodies(): iterable
    {
        foreach ([[], ['estado' => null], ['estado' => 1], ['estado' => []], ['estado' => 'CONFIRMADA'],
            ['estado' => ' confirmada'], ['estado' => 'otro'], ['estado' => 'confirmada', 'id' => 1]] as $input) {
            yield [$input];
        }
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidBodyNeverConnects(array $input): void
    {
        $controller = new ReservaApiController(static function (): never {
            self::fail('No debe abrirse PDO.');
        }, new ReservaFiltroValidator());
        $this->expectException(ReservaValidationException::class);
        $controller->changeStatus('1', $input);
    }

    #[DataProvider('invalidIds')]
    public function testInvalidIdNeverConnects(string $id): void
    {
        $controller = new ReservaApiController(static function (): never {
            self::fail('No debe abrirse PDO.');
        }, new ReservaFiltroValidator());
        $this->expectException(ReservaNotFoundException::class);
        $controller->changeStatus($id, ['estado' => 'confirmada']);
    }

    public static function invalidIds(): iterable
    {
        foreach (['0', '000', '-1', '+1', '12abc', '1.0', '4294967296', str_repeat('9', 100), "12\0", "12\n"] as $id) {
            yield [$id];
        }
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidServiceBodyDoesNotStartTransaction(array $input): void
    {
        $pdo = new class extends PDO {
            public function __construct() {}
            public function beginTransaction(): bool
            {
                throw new LogicException('No debe iniciarse una transacción.');
            }
        };
        $service = new App\Service\ReservaService(new App\Repository\ReservaRepository($pdo), new App\Repository\ReservaEventoRepository($pdo));
        $this->expectException(ReservaValidationException::class);
        $service->changeStatus(1, $input);
    }

    public function testRecognizedStatesAreFieldValidIncludingPending(): void
    {
        foreach (['pendiente', 'confirmada', 'cancelada'] as $estado) {
            self::assertSame([], (new ReservaValidator())->validateStatus(['estado' => $estado]));
        }
    }

    public function testStatusRouteRejectsOtherMethodsWithoutCallingHandler(): void
    {
        $router = new Router();
        $router->add('PATCH', '/api/reservas/{id}/estado', static function (): never {
            self::fail('No se debe ejecutar el cambio.');
        });
        foreach (['GET', 'HEAD', 'POST', 'DELETE'] as $method) {
            ob_start();
            try {
                $router->dispatch($method, '/api/reservas/1/estado');
                self::assertSame(405, http_response_code());
            } finally {
                ob_end_clean();
            }
        }
    }

    public function testPatchRouteExtractsId(): void
    {
        $router = new Router();
        $router->add('PATCH', '/api/reservas/{id}/estado', static function (string $id): void {
            self::assertSame('00012', $id);
            echo 'ok';
        });
        $this->expectOutputString('ok');
        $router->dispatch('PATCH', '/api/reservas/00012/estado?x=1');
    }
}
