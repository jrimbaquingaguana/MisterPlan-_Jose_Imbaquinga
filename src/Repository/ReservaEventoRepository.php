<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ReservaEventoRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findByReservaId(int $id): array
    {
        $query = $this->connection->prepare('SELECT id, reserva_id, tipo, descripcion, fecha_creacion'
            . ' FROM reserva_evento WHERE reserva_id = :id ORDER BY fecha_creacion ASC, id ASC');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['reserva_id'] = (int) $row['reserva_id'];
        }
        return $rows;
    }
}
