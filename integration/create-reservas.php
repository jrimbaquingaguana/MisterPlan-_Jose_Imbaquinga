<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controller\ReservaApiController;
use App\Repository\ReservaEventoRepository;
use App\Repository\ReservaRepository;
use App\Service\ReservaService;
use App\Validation\ReservaFiltroValidator;

$read = static function (string $key): string {
    $value = getenv('MISTERPLAN_TEST_' . $key);
    if ($value === false || $value === '') {
        throw new RuntimeException('Falta MISTERPLAN_TEST_' . $key . '. Integración pendiente.');
    }
    return $value;
};
$name = $read('DB_NAME');
if (preg_match('/\A[a-zA-Z0-9_]+_test\z/', $name) !== 1) {
    throw new RuntimeException('La base exclusiva y vacía debe terminar en _test.');
}
$host = $read('DB_HOST');
$port = filter_var($read('DB_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
if ($port === false || preg_match('/\A[a-zA-Z0-9.:-]+\z/', $host) !== 1) {
    throw new RuntimeException('Host o puerto de pruebas no válido.');
}
$pdo = new class("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $read('DB_USER'), $read('DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]) extends PDO {
    public bool $failEvent = false;
    public ?int $attemptedId = null;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failEvent && str_starts_with($query, 'INSERT INTO reserva_evento ')) {
            $this->attemptedId = (int) $this->lastInsertId();
            return parent::prepare('INSERT INTO reserva_evento (columna_inexistente) VALUES (1)', $options);
        }
        return parent::prepare($query, $options);
    }
};
if ($pdo->query('SHOW TABLES')->fetchAll() !== []) {
    throw new RuntimeException('Se exige un esquema de pruebas vacío; no se modificarán tablas existentes.');
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$created = [];
try {
    $sql = file_get_contents(dirname(__DIR__) . '/database/misterplan_reservas.sql');
    foreach (['reserva', 'reserva_evento'] as $table) {
        if (preg_match('/CREATE TABLE `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $sql, $match) !== 1) {
            throw new RuntimeException('No se encontró la definición de ' . $table);
        }
        $pdo->exec($match[0]);
        $created[] = $table;
    }
    $service = new ReservaService(new ReservaRepository($pdo), new ReservaEventoRepository($pdo));
    $input = [
        'nombre_huesped' => ' <b>Prueba sintética</b> ',
        'email_huesped' => ' fase6@example.com ',
        'nombre_alojamiento' => ' Alojamiento de prueba ',
        'fecha_entrada' => '2027-01-01',
        'fecha_salida' => '2027-01-02',
        'importe' => '000320',
        'notas' => '  ',
    ];
    $original = $input;
    $result = $service->create($input)['data'];
    $assert($input === $original, 'Se modificó la entrada.');
    $assert($result['importe'] === '320.00' && $result['estado'] === 'pendiente', 'Importe o estado incorrecto.');
    $assert($result['nombre_huesped'] === '<b>Prueba sintética</b>' && $result['notas'] === null, 'Normalización incorrecta.');
    $assert(count($result['eventos']) === 1 && $result['eventos'][0]['tipo'] === 'creada', 'Historial incorrecto.');
    $assert($result['eventos'][0]['descripcion'] === 'Reserva creada con estado pendiente.', 'Descripción incorrecta.');
    $assert(!$pdo->inTransaction() && $service->detail($result['id'])['data'] === $result, 'No se confirmó la creación.');

    ob_start();
    try {
        (new ReservaApiController(static fn () => $service, new ReservaFiltroValidator()))->create($input);
        $body = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
    } finally {
        ob_end_clean();
    }
    $assert(http_response_code() === 201 && isset($body['data']['id']), 'Respuesta de creación incorrecta.');
    foreach (['12.5' => '12.50', '0' => '0.00', '99999999.99' => '99999999.99'] as $amount => $expected) {
        $data = $service->create(array_replace($input, ['importe' => (string) $amount, 'notas' => ' nota ']))['data'];
        $assert($data['importe'] === $expected && $data['notas'] === 'nota', 'Importe o notas incorrectos.');
    }
    $before = (int) $pdo->query('SELECT COUNT(*) FROM reserva')->fetchColumn();
    $pdo->failEvent = true;
    try {
        $service->create($input);
        throw new RuntimeException('El evento debería haber fallado.');
    } catch (PDOException $expected) {
        $assert($pdo->attemptedId !== null, 'No se llegó a insertar la reserva.');
        $assert(!$pdo->inTransaction(), 'La transacción sigue abierta.');
        $assert((int) $pdo->query('SELECT COUNT(*) FROM reserva')->fetchColumn() === $before, 'La reserva no se deshizo.');
        $serviceId = (int) $pdo->attemptedId;
        $assert($serviceId > 0, 'No se obtuvo el ID intentado.');
        $assert((int) $pdo->query('SELECT COUNT(*) FROM reserva WHERE id = ' . $serviceId)->fetchColumn() === 0, 'La reserva fallida persiste.');
    }
    $pdo->failEvent = false;
    require __DIR__ . '/status-checks.php';
    echo "OK: creación, controlador 201, normalización, historial y rollback real MySQL.\n";
} finally {
    foreach (array_reverse($created) as $table) {
        $pdo->exec('DROP TABLE `' . $table . '`');
    }
}
