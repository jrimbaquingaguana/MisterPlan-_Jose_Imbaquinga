<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\ReservaNotFoundException;
use App\Repository\ReservaEventoRepository;
use App\Repository\ReservaRepository;

final class ReservaService
{
    public function __construct(private ReservaRepository $reservas, private ReservaEventoRepository $eventos)
    {
    }

    public function list(array $filters, int $page, int $perPage): array
    {
        $total = $this->reservas->count($filters);
        return [
            'data' => $this->reservas->findAll($filters, $perPage, ($page - 1) * $perPage),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => intdiv($total, $perPage) + ($total % $perPage === 0 ? 0 : 1),
            ],
        ];
    }

    public function detail(int $id): array
    {
        $reserva = $this->reservas->findById($id);
        if ($reserva === null) {
            throw new ReservaNotFoundException();
        }
        $reserva['eventos'] = $this->eventos->findByReservaId($id);
        return ['data' => $reserva];
    }

    public function create(array $input): array
    {
        $errors = (new \App\Validation\ReservaValidator())->validateCreate($input);
        if ($errors !== []) {
            throw new \App\Domain\ReservaValidationException($errors);
        }
        $data = [];
        foreach (['nombre_huesped', 'email_huesped', 'nombre_alojamiento'] as $field) {
            $data[$field] = trim($input[$field]);
        }
        $data['fecha_entrada'] = $input['fecha_entrada'];
        $data['fecha_salida'] = $input['fecha_salida'];
        [$integer, $decimal] = array_pad(explode('.', (string) $input['importe'], 2), 2, '');
        $data['importe'] = (ltrim($integer, '0') ?: '0') . '.' . str_pad($decimal, 2, '0');
        $data['notas'] = isset($input['notas']) ? trim($input['notas']) : null;
        if ($data['notas'] === '') {
            $data['notas'] = null;
        }
        $connection = $this->reservas->connection();
        if ($connection !== $this->eventos->connection()) {
            throw new \LogicException('Los repositorios deben compartir PDO.');
        }
        try {
            $connection->beginTransaction();
            $id = $this->reservas->insert($data);
            $this->eventos->insertCreated($id);
            $result = $this->detail($id);
            $connection->commit();
            return $result;
        } catch (\Throwable $error) {
            try {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
            } catch (\Throwable $rollbackError) {
            }
            throw $error;
        }
    }
}
