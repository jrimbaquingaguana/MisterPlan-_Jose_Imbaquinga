<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ReservaRepository
{
    private const COLUMNS = 'id, nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas, fecha_creacion';

    public function __construct(private PDO $connection)
    {
    }

    public function findAll(array $filters, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->conditions($filters);
        $query = $this->connection->prepare('SELECT ' . self::COLUMNS . ' FROM reserva' . $where
            . ' ORDER BY fecha_entrada DESC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($parameters as $name => $value) {
            $query->bindValue($name, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return array_map([$this, 'castRow'], $query->fetchAll());
    }

    public function connection(): PDO
    {
        return $this->connection;
    }

    public function insert(array $data): int
    {
        $query = $this->connection->prepare('INSERT INTO reserva'
            . ' (nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, importe, notas, estado)'
            . " VALUES (:nombre_huesped, :email_huesped, :nombre_alojamiento, :fecha_entrada, :fecha_salida, :importe, :notas, 'pendiente')");
        $query->execute($data);
        return (int) $this->connection->lastInsertId();
    }

    public function count(array $filters): int
    {
        [$where, $parameters] = $this->conditions($filters);
        $query = $this->connection->prepare('SELECT COUNT(*) FROM reserva' . $where);
        $query->execute($parameters);
        return (int) $query->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $query = $this->connection->prepare('SELECT ' . self::COLUMNS . ' FROM reserva WHERE id = :id');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute();
        $row = $query->fetch();
        return $row === false ? null : $this->castRow($row);
    }

    public function findByIdForUpdate(int $id): ?array
    {
        $query = $this->connection->prepare('SELECT ' . self::COLUMNS . ' FROM reserva WHERE id = :id FOR UPDATE');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute();
        $row = $query->fetch();
        return $row === false ? null : $this->castRow($row);
    }

    public function updateStatus(int $id, \App\Domain\EstadoReserva $estado): void
    {
        $query = $this->connection->prepare('UPDATE reserva SET estado = :estado WHERE id = :id');
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':estado', $estado->value, PDO::PARAM_STR);
        $query->execute();
    }

    private function conditions(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if (($filters['estado'] ?? '') !== '') {
            $conditions[] = 'estado = :estado';
            $parameters[':estado'] = $filters['estado'];
        }
        foreach (['desde' => 'fecha_salida > :desde', 'hasta' => 'fecha_entrada <= :hasta'] as $field => $condition) {
            if (($filters[$field] ?? '') !== '') {
                $conditions[] = $condition;
                $parameters[':' . $field] = $filters[$field];
            }
        }
        if (($filters['q'] ?? '') !== '') {
            $conditions[] = "nombre_huesped LIKE :q ESCAPE '!'";
            $parameters[':q'] = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    private function castRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['importe'] = (string) $row['importe'];
        return $row;
    }
}
