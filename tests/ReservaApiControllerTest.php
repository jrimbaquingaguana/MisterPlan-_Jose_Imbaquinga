<?php

declare(strict_types=1);

use App\Controller\ReservaApiController;
use App\Domain\ReservaNotFoundException;
use App\Validation\ReservaFiltroValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservaApiControllerTest extends TestCase
{
    private function controller(): ReservaApiController
    {
        return new ReservaApiController(static function (): never {
            self::fail('No debe abrirse la conexión para entradas inválidas.');
        }, new ReservaFiltroValidator());
    }

    public function testInvalidFiltersReturn400BeforeConnecting(): void
    {
        ob_start();
        try {
            $this->controller()->index(['page' => []]);
            $body = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        self::assertSame(400, http_response_code());
        self::assertArrayHasKey('error', $body);
        self::assertArrayHasKey('page', $body['errors']);
    }

    #[DataProvider('invalidIds')]
    public function testInvalidIdsNeverConnect(string $id): void
    {
        $this->expectException(ReservaNotFoundException::class);
        $this->controller()->show($id);
    }

    public static function invalidIds(): iterable
    {
        foreach (['0', '000', '-1', '+1', '12abc', '1.0', '4294967296', str_repeat('9', 100), "12\0", "12\n"] as $id) {
            yield [$id];
        }
    }

    public function testInvalidCreationNeverConnectsAndAccumulatesErrors(): void
    {
        ob_start();
        try {
            $this->controller()->create(['id' => 1, 'estado' => 'confirmada', 'fecha_creacion' => '2026-01-01']);
            $body = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        self::assertSame(422, http_response_code());
        self::assertArrayHasKey('error', $body);
        self::assertCount(9, $body['errors']);
    }
}
