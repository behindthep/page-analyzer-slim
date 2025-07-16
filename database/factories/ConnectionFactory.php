<?php

namespace Database\Factories;

use Page\Analyzer\Connections\{
    ConnectionInterface,
    LiteConnection,
    PostgreConnection,
};

final class ConnectionFactory
{
    public static function create(string $type): ConnectionInterface
    {
        switch ($type) {
            case 'sqlite':
                return new LiteConnection();
            case 'pgsql':
                return new PostgreConnection();
            default:
                throw new \InvalidArgumentException("Unknown database type: $type");
        }
    }
}
