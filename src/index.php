<?php

namespace Page\Analyzer;

require_once __DIR__ . '/../vendor/autoload.php';

$dao = new UserDAO();

$dao->createTable();

$user1 = new User('John', '555-1234');
$dao->save($user1);

$user1->setUsername('John junior');
$dao->save($user1);

$dao->selectAll();
