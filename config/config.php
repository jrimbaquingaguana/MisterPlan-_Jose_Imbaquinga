<?php

declare(strict_types=1);

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$read = static function (string $name): string {
    $value = getenv($name);
    if ($value === false) {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    }
    if (!is_string($value) || $value === '') {
        throw new RuntimeException('Variable de configuración ausente: ' . $name);
    }
    return $value;
};

$port = static function (string $name) use ($read): int {
    $value = filter_var($read($name), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($value === false) {
        throw new RuntimeException('Puerto de configuración inválido: ' . $name);
    }
    return $value;
};

$debug = filter_var($read('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($debug === null) {
    throw new RuntimeException('APP_DEBUG debe ser un booleano válido.');
}

return [
    'app' => [
        'env' => $read('APP_ENV'),
        'debug' => $debug,
        'port' => $port('APP_PORT'),
    ],
    'db' => [
        'host' => $read('DB_HOST'),
        'port' => $port('DB_PORT'),
        'name' => $read('DB_NAME'),
        'user' => $read('DB_USER'),
        'password' => $read('DB_PASSWORD'),
    ],
];
