<?php

declare(strict_types=1);

use App\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    #[DataProvider('requests')]
    public function testDynamicRoutes(string $method, string $uri, int $status, string $body, bool $called): void
    {
        $router = new Router();
        $invoked = false;
        $router->add('GET', '/api/reservas/{id}', static function (string $id) use (&$invoked): void {
            $invoked = true;
            http_response_code(200);
            echo 'id=' . $id;
        });
        $router->add('GET', '/api/reservas/exacta', static function (): void {
            http_response_code(200);
            echo 'exacta';
        });
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = $method;
        ob_start();
        try {
            $router->dispatch($method, $uri);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
            if ($previous === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previous;
            }
        }
        self::assertSame($status, http_response_code());
        self::assertSame($called, $invoked);
        self::assertSame($body, $output);
    }

    public static function requests(): iterable
    {
        yield ['GET', '/api/reservas/12?x=1', 200, 'id=12', true];
        yield ['GET', '/api/reservas/12abc', 200, 'id=12abc', true];
        yield ['GET', '/api/reservas/{id}', 200, 'id={id}', true];
        yield ['HEAD', '/api/reservas/12', 200, '', true];
        yield ['POST', '/api/reservas/12', 405, '{"error":"Método no permitido"}', false];
        yield ['GET', '/api/reservas/', 404, '{"error":"Ruta no encontrada"}', false];
        yield ['GET', '/api/reservas/12/extra', 404, '{"error":"Ruta no encontrada"}', false];
        yield ['GET', '/api/reservas/exacta', 200, 'exacta', false];
    }
}
