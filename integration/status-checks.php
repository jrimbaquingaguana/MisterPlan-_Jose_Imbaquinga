<?php

declare(strict_types=1);

$independent = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $read('DB_USER'), $read('DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
foreach ([['confirmada', 'cancelada'], ['cancelada']] as $targets) {
    $id = $service->create($input)['data']['id'];
    foreach ($targets as $target) {
        $before = $service->detail($id)['data'];
        $after = $service->changeStatus($id, ['estado' => $target])['data'];
        $event = $after['eventos'][count($after['eventos']) - 1];
        $assert($after['estado'] === $target && $event['tipo'] === $target, 'Estado o evento incorrecto.');
        $assert(count($after['eventos']) === count($before['eventos']) + 1, 'Debe insertarse un único evento.');
        $assert($event['descripcion'] === "Estado cambiado de {$before['estado']} a $target.", 'Descripción incorrecta.');
        $assert($after['importe'] === '320.00' && !$pdo->inTransaction(), 'Respuesta o commit incorrecto.');
        foreach (array_unique([$target, 'pendiente', ...($target === 'cancelada' ? ['confirmada'] : [])]) as $rejected) {
            try {
                $service->changeStatus($id, ['estado' => $rejected]);
                throw new RuntimeException('Se esperaba conflicto.');
            } catch (App\Domain\ReservaConflictException $expected) {
                $assert($service->detail($id)['data'] === $after, 'Un conflicto modificó la reserva o historial.');
            }
        }
    }
}
try {
    $service->changeStatus(4294967295, ['estado' => 'confirmada']);
    throw new RuntimeException('Se esperaba 404.');
} catch (App\Domain\ReservaNotFoundException $expected) {
    $assert(!$pdo->inTransaction(), '404 dejó abierta la transacción.');
}
$id = $service->create($input)['data']['id'];
$original = $service->detail($id)['data'];
$pdo->failEvent = true;
try {
    try {
        $service->changeStatus($id, ['estado' => 'confirmada']);
        throw new RuntimeException('Se esperaba fallo SQL del evento.');
    } catch (PDOException $expected) {
        $observer = new App\Service\ReservaService(new App\Repository\ReservaRepository($independent), new App\Repository\ReservaEventoRepository($independent));
        $assert(!$pdo->inTransaction() && $observer->detail($id)['data'] === $original, 'Rollback no visible desde otra conexión.');
    }
} finally {
    $pdo->failEvent = false;
}
ob_start();
try {
    (new App\Controller\ReservaApiController(static fn () => $service, new App\Validation\ReservaFiltroValidator()))
        ->changeStatus((string) $id, ['estado' => 'confirmada']);
    $response = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
} finally {
    ob_end_clean();
}
$assert(http_response_code() === 200 && $response === $service->detail($id), 'Respuesta 200 incorrecta.');

foreach ([['confirmada', 'confirmada'], ['confirmada', 'cancelada'], ['cancelada', 'confirmada']] as $targets) {
    $id = $service->create($input)['data']['id'];
    $workers = [];
    $pdo->beginTransaction();
    (new App\Repository\ReservaRepository($pdo))->findByIdForUpdate($id);
    try {
        foreach ($targets as $target) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/status-worker.php', (string) $id, $target],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2], 'text' => ''];
        }
        $deadline = microtime(true) + 5;
        do {
            foreach ($workers as &$worker) {
                $worker['text'] .= stream_get_contents($worker['out']);
            }
            unset($worker);
            $waiting = [];
            foreach ($independent->query('SHOW FULL PROCESSLIST')->fetchAll() as $processRow) {
                foreach ($workers as $index => $worker) {
                    if (preg_match('/READY ([0-9]+)/', $worker['text'], $match) === 1
                        && (int) $processRow['Id'] === (int) $match[1]
                        && str_contains((string) $processRow['Info'], 'FOR UPDATE')) {
                        $waiting[$index] = true;
                    }
                }
            }
            if (count($waiting) === 2) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $assert(count($waiting) === 2, 'No se observó competencia simultánea por la fila.');
        $pdo->commit();
        $deadline = microtime(true) + 10;
        do {
            $running = false;
            foreach ($workers as &$worker) {
                $worker['text'] .= stream_get_contents($worker['out']);
                $running = proc_get_status($worker['process'])['running'] || $running;
            }
            unset($worker);
            if (!$running) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $assert(!$running, 'Tiempo de espera de concurrencia agotado.');
        $statuses = [];
        foreach ($workers as $worker) {
            $text = $worker['text'] . stream_get_contents($worker['out']);
            $assert(stream_get_contents($worker['err']) === '', 'El proceso concurrente falló.');
            $assert(preg_match('/\n(200|409)\n\z/', $text, $match) === 1, 'Resultado concurrente incompleto.');
            $statuses[] = (int) $match[1];
        }
        $data = $service->detail($id)['data'];
        $types = array_column($data['eventos'], 'tipo');
        if ($targets === ['confirmada', 'confirmada']) {
            sort($statuses);
            $assert($statuses === [200, 409] && $types === ['creada', 'confirmada'], 'Doble confirmación inconsistente.');
        } else {
            $assert(($types === ['creada', 'confirmada', 'cancelada'] && $statuses === [200, 200])
                || ($types === ['creada', 'cancelada'] && $statuses === ($targets[0] === 'cancelada' ? [200, 409] : [409, 200])),
                'El resultado no corresponde a un orden válido.');
            $assert($data['estado'] === 'cancelada', 'Estado final incorrecto.');
        }
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($workers as $worker) {
            if (proc_get_status($worker['process'])['running']) {
                proc_terminate($worker['process']);
            }
            fclose($worker['out']);
            fclose($worker['err']);
            proc_close($worker['process']);
        }
    }
}
echo "OK: estados, conflictos, detalle 200, rollback independiente y concurrencia real MySQL.\n";
