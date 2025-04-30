<?php

namespace Page\Analyzer;

class UrlRepository
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getEntities(): array
    {
        $sql = "SELECT u.id, u.name, c.created_at, c.response_code
                FROM urls u
                LEFT JOIN checks c ON u.id = c.url
                ORDER BY c.created_at DESC";
        $stmt = $this->conn->query($sql);

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = new UrlCheck(
                $row['id'],
                $row['name'],
                $row['created_at'] ?? null,
                $row['response_code'] ?? null
            );
        }

        return $results;
    }

    // Принцип создания методов для работы с базой одинаковый:
    public function find(int $id): ?Url
    {
        // 1) Описываем шаблон запроса
        $sql = "SELECT * FROM urls WHERE id = ?";
        // 2) Формируем стейтмент
        $stmt = $this->conn->prepare($sql);
        // 3) Делаем подстановки
        // 4) Выполняем запрос
        $stmt->execute([$id]);

        // 5) Собираем результат
        if ($row = $stmt->fetch()) {
            $url = Url::fromArray([$row['name'], $row['created_at']]);
            $url->setId($row['id']);
            $url->setCreatedAt($row['created_at']);
            // 6) Возвращаем ответ
            return $url;
        }

        return null;
    }

    public function findByName(string $name): ?Url
    {
        $sql = "SELECT * FROM urls WHERE name = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$name]);

        if ($row = $stmt->fetch()) {
            $url = Url::fromArray([$row['name'], $row['created_at']]);
            $url->setId($row['id']);
            return $url;
        }

        return null;
    }

    public function save(Url $url): void
    {
        $this->create($url);
    }

    private function create(Url $url): void
    {
        $sql = "INSERT INTO urls (name) VALUES (:name)";
        $stmt = $this->conn->prepare($sql);
        $name = $url->getName();
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $id = (int) $this->conn->lastInsertId();
        $url->setId($id);
    }
}
