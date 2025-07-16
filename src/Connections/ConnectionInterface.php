<?php

namespace Page\Analyzer\Connections;

interface ConnectionInterface
{
    public function get(): \PDO;
}
