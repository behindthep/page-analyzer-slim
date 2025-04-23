<?php

namespace Page\Analyzer;

function connect(string $host, string $db, string $user, string $password): \PDO
{
    try {
        $dsn = "pgsql:host=$host;port=5432;dbname=$db;";

        return new \PDO(
            $dsn,
            $user,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    } catch (\PDOException $e) {
        die($e->getMessage());
    }
}
