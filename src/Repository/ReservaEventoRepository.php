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

    public function connection(): PDO
    {
        return $this->connection;
    }

    public function insertCreated(int $reservaId): void
    {
        $query = $this->connection->prepare('INSERT INTO reserva_evento (reserva_id, tipo, descripcion)'
            . " VALUES (:reserva_id, 'creada', 'Reserva creada con estado pendiente.')");
        $query->bindValue(':reserva_id', $reservaId, PDO::PARAM_INT);
        $query->execute();
    }

    public function insertStatusChanged(int $reservaId, \App\Domain\EstadoReserva $from, \App\Domain\EstadoReserva $to): void
    {
        $query = $this->connection->prepare('INSERT INTO reserva_evento (reserva_id, tipo, descripcion)'
            . ' VALUES (:reserva_id, :tipo, :descripcion)');
        $query->bindValue(':reserva_id', $reservaId, PDO::PARAM_INT);
        $query->bindValue(':tipo', $to->value, PDO::PARAM_STR);
        $query->bindValue(':descripcion', 'Estado cambiado de ' . $from->value . ' a ' . $to->value . '.', PDO::PARAM_STR);
        $query->execute();
    }
}
