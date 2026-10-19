<?php

declare(strict_types=1);

use App\Http\ErrorHandler;
use App\Http\JsonResponse;
use App\Http\HtmlResponse;
use App\Http\Router;
use App\Controller\ReservaApiController;
use App\Controller\ReservaViewController;
use App\Database\Connection;
use App\Repository\ReservaRepository;
use App\Repository\ReservaEventoRepository;
use App\Service\ReservaService;
use App\Validation\ReservaFiltroValidator;

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require dirname(__DIR__) . '/vendor/autoload.php';
ErrorHandler::register();
$config = require dirname(__DIR__) . '/config/config.php';

$views = HtmlResponse::create();
$router = new Router(static function (string $message, int $status, array $headers) use ($views): void {
    if (ErrorHandler::isApiRequest()) {
        JsonResponse::send(['error' => $message], $status, $headers);
    } else {
        $views->error($message, $status, $headers);
    }
});
$router->add('GET', '/', static function (): void {
    header('Location: /reservas', true, 302);
    header('X-Content-Type-Options: nosniff');
});

$router->add('GET', '/api/health', static function (): void {
    JsonResponse::send(['status' => 'ok']);
});

$createService = static function () use ($config): ReservaService {
    $connection = Connection::create($config['db']);
    return new ReservaService(new ReservaRepository($connection), new ReservaEventoRepository($connection));
};
$controller = new ReservaApiController($createService, new ReservaFiltroValidator());
$viewController = new ReservaViewController($createService, new ReservaFiltroValidator(), $views);
$router->add('GET', '/reservas', static function () use ($viewController): void {
    $viewController->index($_GET);
});
$router->add('GET', '/reservas/nueva', [$viewController, 'new']);
$router->add('GET', '/reservas/{id}', static function (string $id) use ($viewController): void {
    $viewController->show($id, $_GET);
});
$router->add('GET', '/assets/css/app.css', static function (): void {
    header('Content-Type: text/css; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/assets/css/app.css');
});
foreach (['api.js', 'reserva-form.js'] as $asset) {
    $router->add('GET', '/assets/js/' . $asset, static function () use ($asset): void {
        header('Content-Type: text/javascript; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        readfile(__DIR__ . '/assets/js/' . $asset);
    });
}
$router->add('GET', '/api/reservas', static function () use ($controller): void {
    $controller->index($_GET);
});
$router->add('GET', '/api/reservas/{id}', [$controller, 'show']);
$router->add('POST', '/api/reservas', static function () use ($controller): void {
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        throw new RuntimeException('No se pudo abrir la petición.');
    }
    try {
        $input = \App\Http\JsonRequest::read($_SERVER['CONTENT_TYPE'] ?? '', $stream);
    } finally {
        fclose($stream);
    }
    $controller->create($input);
});

$router->add('PATCH', '/api/reservas/{id}/estado', static function (string $id) use ($controller): void {
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        throw new RuntimeException('No se pudo abrir la petición.');
    }
    try {
        $input = \App\Http\JsonRequest::read($_SERVER['CONTENT_TYPE'] ?? '', $stream);
    } finally {
        fclose($stream);
    }
    $controller->changeStatus($id, $input);
});

$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
if ($path === '/api/reservas' || str_starts_with($path, '/api/reservas/')) {
    header('Cache-Control: no-store');
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
