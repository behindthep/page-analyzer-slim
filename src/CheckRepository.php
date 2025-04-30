<?php

namespace Page\Analyzer;

class CheckRepository
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(): array
    {
        $check = [];
        $sql = "SELECT * FROM checks";
        $stmt = $this->conn->query($sql);

        while ($row = $stmt->fetch()) {
            $check = Check::fromArray(
                [$row['response_code'], $row['header'], $row['title'], $row['description'], $row['created_at']]
            );
            $check->setId($row['id']);
            $check[] = $check;
        }

        return $check;
    }

    public function find(int $id): ?Check
    {
        $sql = "SELECT * FROM checks WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        if ($row = $stmt->fetch()) {
            $check = Check::fromArray(
                [$row['response_code'], $row['header'], $row['title'], $row['description'], $row['created_at']]
            );
            $check->setId($row['id']);
            $check->setCreatedAt($row['created_at']);
            return $check;
        }

        return null;
    }

    public function save(Check $check): void
    {
        $this->create($check);
    }

    private function create(Check $check): void
    {
        $sql = "INSERT INTO checks (response_code, header, title, description) 
        VALUES (:response_code, :header, :title, :description)";
        $stmt = $this->conn->prepare($sql);

        $responseCode = $check->getResponseCode();
        $header = $check->getHeader();
        $title = $check->getTitle();
        $description = $check->getDescription();

        $stmt->bindParam(':response_code', $responseCode);
        $stmt->bindParam(':header', $header);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);

        $stmt->execute();
        $id = (int) $this->conn->lastInsertId();
        $check->setId($id);
    }
}
