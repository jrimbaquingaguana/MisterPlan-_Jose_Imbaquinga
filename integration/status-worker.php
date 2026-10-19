<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$name = getenv('MISTERPLAN_TEST_DB_NAME');
if (!is_string($name) || preg_match('/\A[a-zA-Z0-9_]+_test\z/', $name) !== 1) {
    throw new RuntimeException('Se exige una base exclusiva _test.');
}
$pdo = new PDO('mysql:host=' . getenv('MISTERPLAN_TEST_DB_HOST') . ';port=' . getenv('MISTERPLAN_TEST_DB_PORT')
    . ';dbname=' . $name . ';charset=utf8mb4', getenv('MISTERPLAN_TEST_DB_USER'), getenv('MISTERPLAN_TEST_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$pdo->exec('SET SESSION innodb_lock_wait_timeout = 8');
$service = new App\Service\ReservaService(new App\Repository\ReservaRepository($pdo), new App\Repository\ReservaEventoRepository($pdo));
echo 'READY ' . $pdo->query('SELECT CONNECTION_ID()')->fetchColumn() . "\n";
flush();
try {
    $service->changeStatus((int) $argv[1], ['estado' => $argv[2]]);
    echo "200\n";
} catch (App\Domain\ReservaConflictException $error) {
    echo "409\n";
}
