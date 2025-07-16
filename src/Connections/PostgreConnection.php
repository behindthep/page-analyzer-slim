<?php

namespace Page\Analyzer\Connections;

final class PostgreConnection implements ConnectionInterface
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
    }

    public function get(): \PDO
    {
        return $this->pdo;
    }
}
