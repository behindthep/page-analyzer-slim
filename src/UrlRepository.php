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

    public function getEntities(): array
    {
        $sql = "SELECT *
                FROM urls 
                ORDER BY created_at";

        $stmt = $this->conn->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT *
                FROM urls 
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'id' => $id
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByName(string $name): ?array
    {
        $sql = "SELECT *
                FROM urls
                WHERE name = :name";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'name' => $name
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function save(string $name): int
    {
        /**
         * @return id of the inserted record
         */
        $sql = "INSERT INTO urls (name, created_at)
                VALUES (:name, :created_at) RETURNING id";

        $stmt = $this->conn->prepare($sql);
        $date = Carbon::now();
        $stmt->execute([
            'name' => $name,
            'created_at' => $date
        ]);
        /**
         * @return id (the first and only value) of the first column (id)
         */
        $id = $stmt->fetchColumn();
        return (int) $id;
    }
}
