<?php

namespace Page\Analyzer\Connections;

final class LiteConnection implements ConnectionInterface
{
    private \PDO $pdo;

    public function __construct()
    {
        $dbPath = __DIR__ . '/../database/database.sqlite';

        $dsn = "sqlite:$dbPath";

        $this->pdo = new \PDO($dsn);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
    }

    public function get(): \PDO
    {
        return $this->pdo;
    }
}
