<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require dirname(__DIR__) . '/vendor/autoload.php';
    $config = require dirname(__DIR__) . '/config/config.php';
} catch (Throwable $error) {
    error_log('Fallo de arranque de MisterPlan: ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>MisterPlan</title><main><h1>Servicio temporalmente no disponible</h1><p>Inténtalo de nuevo más tarde.</p></main></html>';
    exit;
}

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
