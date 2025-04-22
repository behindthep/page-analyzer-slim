<?php

namespace Page\Analyzer;

// use Psr\Http\Message\ResponseInterface as Response;
// use Psr\Http\Message\ServerRequestInterface as Request;
// use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// $app = AppFactory::create();

// $app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
//     $name = $args['name'];
//     $response->getBody()->write("Hello, $name");
//     return $response;
// });

// $app->run();

$conn = new \PDO('sqlite::memory:');
$conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

$sqlCreateU = "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, phone TEXT)";
$conn->exec($sqlCreateU);

$sqlInsert = "INSERT INTO users (username, phone) VALUES (:name, :phone)";
$stmtPrepI = $conn->prepare($sqlInsert);
$stmtPrepI->bindParam(':name', $name);
$stmtPrepI->bindParam(':phone', $phone);

try {
    $conn->beginTransaction();
    $conn->exec("INSERT INTO users (username, phone) VALUES ('Joe', '1234')");
    $conn->exec("INSERT INTO users (username, phone) VALUES ('Gally', '77542')");
    $conn->commit();
} catch (\Exception $e) {
    $conn->rollBack();
    echo "Ошибка:  {$e->getMessage()}";
}

$sqlSelect = "SELECT * FROM users";
$stmtQueryS = $conn->query($sqlSelect);

$dao = new UserDAO($conn);
$user1 = new User('John', '555-1234');
$dao->save($user1);

$user1->setUsername('John junior');
$dao->save($user1);

$user2 = $dao->find($user1->getId());
dump($user2->getId() == $user1->getId()); // true

$dao->delete($user1);

print_r($stmtQueryS->fetchAll());
