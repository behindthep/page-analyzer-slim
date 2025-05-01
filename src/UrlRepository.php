<?php

namespace Page\Analyzer;

use Carbon\Carbon;

class UrlRepository
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function findByName(string $name): ?array
    {
        $sql  = "SELECT * FROM urls WHERE name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(
            ['name' => $name]
        );
        // row
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function save(string $name): int
    {
        $sql = "INSERT INTO urls (name, created_at)
                VALUES (:name, :created_at) RETURNING id";
        // $sql = "INSERT INTO urls (name, created_at)
        //         VALUES (:name, :created_at)";
        $stmt = $this->conn->prepare($sql);
        $date = Carbon::now();
        $stmt->execute(
            [
            'name' => $name,
            'created_at' => $date
            ]
        );

        // first (id) single column
        // значение первого столбца из первой строки результата запроса
        // - значение id, которое было возвращено после вставки.
        $id = $stmt->fetchColumn();
        // $id = $this->conn->lastInsertId();
        // Зачем int?
        return (int) $id;
    }

    public function findById(int $id): ?array // mixed was
    {
        $sql = "SELECT * FROM urls WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getEntities(): array
    {
        $sql = 'SELECT * FROM urls ORDER BY created_at';
        $stmt = $this->conn->query($sql);
        // без fetach assoc, оно по дефу тут по идее
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }
}
