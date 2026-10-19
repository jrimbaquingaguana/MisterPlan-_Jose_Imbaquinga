<?php

declare(strict_types=1);

use App\Http\ErrorHandler;
use App\Http\JsonResponse;
use App\Http\Router;
use App\Controller\ReservaApiController;
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

$router = new Router();
$router->add('GET', '/', static function (): void {
    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MisterPlan — Gestión de reservas</title>
</head>
<body>
    <main>
        <h1>MisterPlan — Gestión de reservas</h1>
        <p>La configuración inicial está lista.</p>
    </main>
</body>
</html>
<?php
});

$router->add('GET', '/api/health', static function (): void {
    JsonResponse::send(['status' => 'ok']);
});

$controller = new ReservaApiController(static function () use ($config): ReservaService {
    $connection = Connection::create($config['db']);
    return new ReservaService(new ReservaRepository($connection), new ReservaEventoRepository($connection));
}, new ReservaFiltroValidator());
$router->add('GET', '/api/reservas', static function () use ($controller): void {
    $controller->index($_GET);
});
$router->add('GET', '/api/reservas/{id}', [$controller, 'show']);

$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
if ($path === '/api/reservas' || str_starts_with($path, '/api/reservas/')) {
    header('Cache-Control: no-store');
}

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
