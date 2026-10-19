<?php

declare(strict_types=1);

use App\Controller\ReservaViewController;
use App\Domain\ReservaNotFoundException;
use App\Http\ErrorHandler;
use App\Http\HtmlResponse;
use App\Http\Router;
use App\Repository\ReservaEventoRepository;
use App\Repository\ReservaRepository;
use App\Service\ReservaService;
use App\Validation\ReservaFiltroValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ReservaViewTest extends TestCase
{
    public function testNewRouteRendersWithoutDatabaseAndTakesPrecedenceOverDetail(): void
    {
        $controller = new ReservaViewController(static function (): never {
            self::fail('El formulario vacío no debe abrir PDO.');
        }, new ReservaFiltroValidator(), HtmlResponse::create());
        $allowed = null;
        $router = new Router(static function (string $message, int $status, array $headers) use (&$allowed): void {
            $allowed = $headers['Allow'] ?? null;
            HtmlResponse::create()->error($message, $status, $headers);
        });
        $router->add('GET', '/reservas/{id}', static function (): never {
            self::fail('La ruta nueva no debe invocar el detalle.');
        });
        $router->add('GET', '/reservas/nueva', [$controller, 'new']);
        $html = $this->capture(fn () => $router->dispatch('GET', '/reservas/nueva'));
        self::assertSame(200, http_response_code());
        self::assertStringContainsString('<html lang="es">', $html);
        self::assertStringContainsString('method="post" action="/api/reservas"', $html);
        self::assertStringContainsString('type="submit" disabled', $html);
        self::assertStringContainsString('type="module" src="/assets/js/reserva-form.js"', $html);
        preg_match_all('/name="([^"]+)"/', $html, $matches);
        self::assertSame(['viewport', 'nombre_huesped', 'email_huesped', 'nombre_alojamiento',
            'fecha_entrada', 'fecha_salida', 'importe', 'notas'], $matches[1]);
        self::assertSame('', $this->capture(fn () => $router->dispatch('HEAD', '/reservas/nueva')));
        self::assertSame(200, http_response_code());
        $this->capture(fn () => $router->dispatch('POST', '/reservas/nueva'));
        self::assertSame(405, http_response_code());
        self::assertSame('GET, HEAD', $allowed);
    }

    public function testNewTemplateAutoescapesContext(): void
    {
        $html = $this->capture(fn () => HtmlResponse::create()->render('reservas/new.html.twig', [
            'api_url' => '/api/reservas?x=" onfocus="alert(1)',
            'detail_base_url' => '<script>alert(1)</script>',
        ]));
        self::assertStringContainsString('&quot; onfocus=&quot;', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString(' onfocus="', $html);
    }

    private function reserva(): array
    {
        return [
            'id' => 12, 'nombre_huesped' => '<script>alert("huésped")</script>',
            'email_huesped' => 'prueba@example.com', 'nombre_alojamiento' => 'Casa & Mar',
            'fecha_entrada' => '2027-01-01', 'fecha_salida' => '2027-01-02',
            'estado' => 'pendiente', 'importe' => '1234.50', 'notas' => "Primera línea\n<b>Segunda línea</b>",
            'fecha_creacion' => '2026-10-19 09:15:00',
            'eventos' => [[
                'id' => 1, 'reserva_id' => 12, 'tipo' => 'creada',
                'descripcion' => '<b>Reserva creada</b>', 'fecha_creacion' => '2026-10-19 09:15:00',
            ]],
        ];
    }

    private function controller(array $reserva, int $total = 3, bool $empty = false): ReservaViewController
    {
        $pdo = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->onlyMethods(['prepare'])->getMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($reserva, $total, $empty): PDOStatement {
            $statement = $this->createMock(PDOStatement::class);
            $statement->method('bindValue')->willReturn(true);
            $statement->method('execute')->willReturn(true);
            $statement->method('fetchColumn')->willReturn($total);
            $statement->method('fetch')->willReturn($reserva);
            $statement->method('fetchAll')->willReturn(str_contains($sql, 'FROM reserva_evento')
                ? $reserva['eventos'] : ($empty ? [] : [array_diff_key($reserva, ['eventos' => true])]));
            return $statement;
        });
        $service = new ReservaService(new ReservaRepository($pdo), new ReservaEventoRepository($pdo));
        return new ReservaViewController(static fn () => $service, new ReservaFiltroValidator(), HtmlResponse::create());
    }

    private function capture(Closure $action): string
    {
        ob_start();
        try {
            $action();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function testListEscapesDataAndPreservesFiltersInEncodedLinks(): void
    {
        $values = ['estado' => 'pendiente', 'desde' => '2027-01-01', 'hasta' => '2027-01-02',
            'q' => '<b>Ana & Bea</b>', 'page' => '1', 'per_page' => '2'];
        $html = $this->capture(fn () => $this->controller($this->reserva())->index($values));
        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Reservas de alojamiento', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('1.234,50 €', $html);
        self::assertStringContainsString('01/01/2027', $html);
        self::assertStringContainsString('value="&lt;b&gt;Ana &amp; Bea&lt;/b&gt;"', $html);
        self::assertStringContainsString('value="2" selected', $html);
        self::assertStringContainsString('value="pendiente" selected', $html);
        $decoded = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        self::assertStringContainsString('/reservas/12?' . http_build_query($values, '', '&', PHP_QUERY_RFC3986), $decoded);
        self::assertStringContainsString('/reservas?' . http_build_query(array_replace($values, ['page' => 2]), '', '&', PHP_QUERY_RFC3986), $decoded);
        self::assertStringNotContainsString('rel="prev"', $html);
        self::assertStringNotContainsString('name="page"', $html);
        self::assertStringNotContainsString('Primera línea', $html);
    }

    public function testDetailEscapesNotesAndHistoryAndKeepsLineBreaks(): void
    {
        $html = $this->capture(fn () => $this->controller($this->reserva())->show('12', ['q' => 'Ana & Bea', 'page' => '2', 'per_page' => '2']));
        self::assertSame(200, http_response_code());
        self::assertStringContainsString('Volver al listado', $html);
        self::assertStringContainsString('q=Ana%20%26%20Bea&amp;page=2&amp;per_page=2', $html);
        self::assertStringContainsString('<ol class="history">', $html);
        self::assertStringContainsString('&lt;b&gt;Reserva creada&lt;/b&gt;', $html);
        self::assertStringContainsString("Primera línea\n&lt;b&gt;Segunda línea&lt;/b&gt;", $html);
        self::assertStringContainsString('class="notes"', $html);
        self::assertStringContainsString('datetime="2026-10-19T09:15:00"', $html);
        self::assertStringContainsString('19/10/2026 09:15', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testEmptyListAndOutOfRangePageOfferUsefulMessages(): void
    {
        $html = $this->capture(fn () => $this->controller($this->reserva(), 0, true)->index([]));
        self::assertStringContainsString('No hay reservas que coincidan', $html);
        self::assertStringNotContainsString('rel="next"', $html);
        $html = $this->capture(fn () => $this->controller($this->reserva(), 3, true)->index(['page' => '9', 'per_page' => '2']));
        self::assertStringContainsString('fuera de los resultados', $html);
        self::assertStringContainsString('page=2&amp;per_page=2', $html);
    }

    public function testNullAndEmptyNotesAndEmptyHistory(): void
    {
        foreach ([null, ''] as $notes) {
            $reserva = array_replace($this->reserva(), ['notas' => $notes, 'eventos' => []]);
            $html = $this->capture(fn () => $this->controller($reserva)->show('12', []));
            self::assertStringContainsString('Sin notas', $html);
            self::assertStringContainsString('No hay eventos registrados.', $html);
            self::assertStringNotContainsString('<ol', $html);
        }
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFiltersDoNotConnectOrReflectInvalidValues(array $input): void
    {
        $controller = new ReservaViewController(static function (): never {
            self::fail('Los filtros inválidos no deben abrir PDO.');
        }, new ReservaFiltroValidator(), HtmlResponse::create());
        $html = $this->capture(fn () => $controller->index($input));
        self::assertSame(400, http_response_code());
        self::assertStringContainsString('No se ha realizado la consulta.', $html);
        self::assertStringNotContainsString('EVIL', $html);
    }

    public static function invalidFilters(): iterable
    {
        yield [['q' => ['EVIL']]];
        yield [['estado' => 'EVIL']];
        yield [['hasta' => 'EVIL']];
        yield [['page' => '0', 'per_page' => 'EVIL']];
        yield [['return' => 'https://EVIL.example']];
    }

    public function testInvalidDetailIdAndReturnParametersNeverConnect(): void
    {
        $controller = new ReservaViewController(static function (): never {
            self::fail('No debe abrir PDO.');
        }, new ReservaFiltroValidator(), HtmlResponse::create());
        $html = $this->capture(fn () => $controller->show('12', ['q' => ['EVIL']]));
        self::assertSame(400, http_response_code());
        self::assertStringNotContainsString('EVIL', $html);
        $this->expectException(ReservaNotFoundException::class);
        $controller->show('12abc', []);
    }

    public function testWebErrorsUseHtmlWhileApiErrorsKeepJson(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        try {
            $_SERVER['REQUEST_URI'] = '/reservas/999';
            $html = $this->capture(fn () => ErrorHandler::respond(new ReservaNotFoundException()));
            self::assertSame(404, http_response_code());
            self::assertStringContainsString('<html lang="es">', $html);
            self::assertStringContainsString('Reserva no encontrada', $html);
            $_SERVER['REQUEST_URI'] = '/api/reservas/999';
            $json = $this->capture(fn () => ErrorHandler::respond(new ReservaNotFoundException()));
            self::assertSame(['error' => 'Reserva no encontrada'], json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
        }
    }

    public function testBrokenErrorTemplateHasSafeFallbackAndHeadHasNoBody(): void
    {
        $views = new HtmlResponse(new Environment(new ArrayLoader(['error.html.twig' => '{{ missing.secret }}']), ['strict_variables' => true]));
        $html = $this->capture(fn () => $views->error('SQL secret', 500));
        self::assertSame(500, http_response_code());
        self::assertStringContainsString('No se pudo completar', $html);
        self::assertStringNotContainsString('secret', $html);
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        try {
            $_SERVER['REQUEST_METHOD'] = 'HEAD';
            self::assertSame('', $this->capture(fn () => $views->error('Error', 500)));
        } finally {
            if ($previousMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previousMethod;
            }
        }
    }

    public function testUnexpectedWebErrorDoesNotExposeInternalMessage(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        try {
            $_SERVER['REQUEST_URI'] = '/reservas';
            $html = $this->capture(fn () => ErrorHandler::respond(new RuntimeException('SQL credential secret')));
            self::assertSame(500, http_response_code());
            self::assertStringContainsString('Error interno del servidor', $html);
            self::assertStringNotContainsString('credential', $html);
            self::assertStringNotContainsString('RuntimeException', $html);
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
        }
    }

    public function testRouterCanRenderHtmlErrorsWithoutInvokingRoutes(): void
    {
        $router = new Router(static fn (string $message, int $status, array $headers) => HtmlResponse::create()->error($message, $status, $headers));
        $router->add('GET', '/reservas', static function (): never { self::fail('No debe ejecutar GET.'); });
        $html = $this->capture(fn () => $router->dispatch('POST', '/reservas'));
        self::assertSame(405, http_response_code());
        self::assertStringContainsString('Método no permitido', $html);
        $html = $this->capture(fn () => $router->dispatch('GET', '/ruta-inexistente'));
        self::assertSame(404, http_response_code());
        self::assertStringContainsString('Ruta no encontrada', $html);
    }
}
