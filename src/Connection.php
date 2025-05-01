<?php

namespace Page\Analyzer;

final class Connection
{
    private \PDO $pdo;

    public function __construct()
    {
        $databaseUrl = $_ENV['DATABASE_URL'];
        $parsedDbUrl = parse_url($databaseUrl);

        $host     = $parsedDbUrl['host'] ?? '';
        $port     = $parsedDbUrl['port'] ?? '5432';
        $dbname   = ltrim($parsedDbUrl['path'], '/');
        $username = $parsedDbUrl['user'] ?? '';
        $password = $parsedDbUrl['pass'] ?? '';

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

        $this->pdo = new \PDO($dsn, $username, $password);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $this->initializeDatabase();
    }

    private function initializeDatabase(): void
    {
        $sqlFilePath = __DIR__ . '/../database.sql';

        if (!file_exists($sqlFilePath)) {
            throw new \RuntimeException("SQL file not found: $sqlFilePath");
        }

        $sql = file_get_contents($sqlFilePath);

        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            error_log("Ошибка создания таблиц: " . $e->getMessage());
            throw new \RuntimeException("Ошибка инициализации базы данных.");
        }
    }

    public function get(): \PDO
    {
        return $this->pdo;
    }
}
