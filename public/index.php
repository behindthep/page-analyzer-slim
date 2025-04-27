<?php
namespace Page\Analyzer;
require_once __DIR__ . '/../vendor/autoload.php';
use Slim\Factory\AppFactory;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});
$app = AppFactory::createFromContainer($container);
// AppFactory::setContainer($container);
// $app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// Doctrine ORM
// $repo = new App\UserRepository(); # Хранилище объектов
// $user = new User();
// $user->setName($newUsername);
// $entityManager->persist($user);
// $entityManager->flush();
// $repo->find($entity['id']); // $entity
// $repo->all(); // [$entity, $entity2] // Извлечение всех сущностей

$app->get('/users', function (Request $request, Response $response) {
    $jsonUsers = file_get_contents(__DIR__ . '/../storage/users.json');
    $users = json_decode($jsonUsers, true);

    $messages = $this->get('flash')->getMessages();

    $params = ['users' => $users, 'flash' => $messages];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function (Request $request, Response $response) {
    $validator = new Validator();
    $user = $request->getParsedBodyParam('user');
    $errors = $validator->validate($user);

    if (count($errors) === 0) {
        // $repo->save($user);
        if (!file_exists(__DIR__ . '/../storage/users.json')) {
            file_put_contents(__DIR__ . '/../storage/users.json', json_encode([]));
        }

        $jsonUsers = file_get_contents(__DIR__ . '/../storage/users.json');
        $users = json_decode($jsonUsers, true);
        $user['id'] = count($users) + 1;
        $users[] = $user;

        file_put_contents(__DIR__ . '/../storage/users.json', json_encode($users));

        $this->get('flash')->addMessage('success', 'User was added');
        return $response->withRedirect('/users', 302);
        return $response->withRedirect("/user/{$user['id']}");
    }
    
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/new', function (Request $request, Response $response) {
    $params = [
        'user' => ['name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => '', 'city' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('users/new');

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $jsonUsers = file_get_contents(__DIR__ . '/../storage/users.json');
    $users = json_decode($jsonUsers, true);

    $filteredUsers = array_filter($users, fn($user) => $user['id'] === (int) $args['id']);
    $user = reset($filteredUsers);

    if (!$user) {
        return $response->withStatus(404)->write('Пользователь не найден');
    }
    $params = ['user' => $user];

    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->run();
