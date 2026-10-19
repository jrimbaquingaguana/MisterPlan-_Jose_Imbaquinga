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
}
