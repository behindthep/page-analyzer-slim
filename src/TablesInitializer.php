<?php

namespace Page\Analyzer;

class TablesInitializer
{
    public function __construct(\PDO $conn)
    {
        $sqlFilePath = __DIR__ . '/../database.sql';

        if (!file_exists($sqlFilePath)) {
            throw new \RuntimeException("SQL file not found: $sqlFilePath");
        }

        $sql = file_get_contents($sqlFilePath);

        try {
            $conn->exec($sql);
        } catch (\PDOException $e) {
            error_log("Error creating tables: " . $e->getMessage());
            throw new \RuntimeException("Database initialization error.");
        }
    }
}
