<?php
namespace Page\Analyzer;

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require_once __DIR__ . '/../vendor/autoload.php';

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
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

// Doctrine ORM
// $repo = new App\UserRepository(); # Хранилище объектов
// $user = new User();
// $user->setName($newUsername);
// $entityManager->persist($user);
// $entityManager->flush();
// $repo->find($entity['id']); // $entity
// $repo->all(); // [$entity, $entity2] // Извлечение всех сущностей

$logMiddleware = function (Request $request, RequestHandler $handler): ResponseInterface {
    error_log("Request: {$request->getMethod()} {$request->getUri()}");

    // Передаем управление дальше
    return $handler->handle($request);
};

$beforeMiddleware = function (Request $request, RequestHandler $handler) use ($app) {
    $id = $request->getQueryParams()['id'] ?? null;

    if (!$id) {
        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write('Missing id parameter');
        return $response->withStatus(400);
    }

    return $handler->handle($request);
};

$afterMiddleware = function (Request $request, RequestHandler $handler) {
    // Передаем управление дальше
    $response = $handler->handle($request);

    $response = $response->withHeader('X-Custom-Header', 'value');

    return $response;
};

// Первой выполнится последняя добавленная logMiddleware
// $app->add($afterMiddleware);
// $app->add($beforeMiddleware);
// $app->add($logMiddleware);

$authMiddleware = function (Request $request, RequestHandler $handler) use ($router, $app): ResponseInterface {

    if (!isset($_SESSION['user'])) {
        $currentUri = $request->getUri()->getPath();

        if ($currentUri !== '/login') {
            $url = $router->urlFor('login');
            $response = $app->getResponseFactory()->createResponse();
            return $response->withHeader('Location', $url)->withStatus(302);
        }
    }
    return $handler->handle($request);
};

$app->add($authMiddleware);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Welcome!");
    return $response;
});

$app->get('/users', function (Request $request, Response $response) {
    // $repository = new App\UserRepository();
    // $users = $repository->all();

    $cookies = $request->getCookieParams();
    $jsonUsers = $cookies['users'] ?? json_encode([]);
    $users = json_decode($jsonUsers, true);

    $messages = $this->get('flash')->getMessages();

    // пэйджинг

    $params = ['users' => $users, 'flash' => $messages];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function (Request $request, Response $response) use ($router) {
    // $repo = new App\UserRepository();
    $user = $request->getParsedBody()['user'] ?? [];
    $validator = new Validator();
    $errors = $validator->validate($user);

    if (count($errors) === 0) {
        $cookies = $request->getCookieParams();
        $jsonUsers = $cookies['users'] ?? json_encode([]);
        $users = json_decode($jsonUsers, true);
        $user['id'] = count($users) + 1;
        $users[] = $user;

        // $repo->save($user);
        
        $this->get('flash')->addMessage('success', 'User has been created');
        
        $encodedUsers = json_encode($users);
        $url = $router->urlFor('users');

        return $response
            ->withHeader('Set-Cookie', "users={$encodedUsers}; Path=/")
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
    
    $params = ['user' => $user, 'errors' => $errors];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/new', function (Request $request, Response $response) {
    $params = [
        'user' => ['name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => '', 'city' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('newUser');

$app->get('/users/{id}', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];
    // $repository = new App\UserRepository();
    // $user = $repository->find($id);

    $cookies = $request->getCookieParams();
    $jsonUsers = $cookies['users'] ?? json_encode([]);
    $users = json_decode($jsonUsers, true);

    $userIndex = array_search($id, array_column($users, 'id'));

    if ($userIndex === false) {
        $response->getBody()->write('Page not found');
        return $response->withStatus(404);
    }

    $user = $users[$userIndex];

    $params = ['user' => $user];

    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/users/{id}/edit', function (Request $request, Response $response, array $args) {
    // $repo = new App\UserRepository();
    $id = (int) $args['id'];
    // $user = $repo->find($id);

    $cookies = $request->getCookieParams();
    $jsonUsers = $cookies['users'] ?? json_encode([]);
    $users = json_decode($jsonUsers, true);

    $userIndex = array_search($id, array_column($users, 'id'));

    if ($userIndex === false) {
        $response->getBody()->write('Page not found');
        return $response->withStatus(404);
    }

    $user = $users[$userIndex];
    
    $params = ['user' => $user, 'errors' => []];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function (Request $request, Response $response, array $args) use ($router)  {
    // $repo = new App\UserRepository();
    $id = (int) $args['id'];
    // $user = $repo->find($id);

    $data = $request->getParsedBody()['user'] ?? [];

    $cookies = $request->getCookieParams();
    $jsonUsers = $cookies['users'] ?? json_encode([]);
    $users = json_decode($jsonUsers, true);

    $userIndex = array_search($id, array_column($users, 'id'));

    if ($userIndex === false) {
        $response->getBody()->write('Page not found');
        return $response->withStatus(404);
    }

    $user = $users[$userIndex];

    $validator = new Validator();
    $errors = $validator->validate($data);

    if (count($errors) === 0) {
        $users[$userIndex]['name'] = $data['name'];
        $users[$userIndex]['email'] = $data['email'];
        $users[$userIndex]['city'] = $data['city'];

        $response = $response->withHeader('Set-Cookie', "users=" . json_encode($users) . "; Path=/");

        $this->get('flash')->addMessage('success', 'User has been updated');
        // $repo->save($user);
        $url = $router->urlFor('users');
        return $response->withHeader('Location', $url)->withStatus(302);
    }

    $params = ['user' => $users[$userIndex], 'errors' => $errors];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->delete('/users/{id}', function (Request $request, Response $response, array $args) use ($router) {
    // $repo = new App\UserRepository();
    $id = (int) $args['id'];
    // $repo->destroy($id);

    $cookies = $request->getCookieParams();
    $jsonUsers = $cookies['users'] ?? json_encode([]);
    $users = json_decode($jsonUsers, true);

    $users = array_values(array_filter($users, fn($user) => $user['id'] !== $id));

    $response = $response->withHeader('Set-Cookie', "users=" . json_encode($users) . "; Path=/");

    $this->get('flash')->addMessage('success', 'User has been deleted');
    $url = $router->urlFor('users');
    return $response->withHeader('Location', $url)->withStatus(302);
});

$app->get('/login', function (Request $request, Response $response) {
    $flashMessages = $this->get('flash')->getMessages();
    $data = $request->getParsedBody()['user'] ?? [];
    $email = $data['email'] ?? '';

    return $this->get('renderer')->render($response, 'login.phtml', [
        'email' => $email,
        'flash' => $flashMessages
    ]);
})->setName('login');

$app->post('/login', function (Request $request, Response $response) {
    $data = $request->getParsedBody()['user'] ?? [];
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if ($email === 'test@mail.ru' && $password === '12345') {
        $_SESSION['user'] = $email; // Сохраняем информацию о пользователе в сессии
        return $response->withHeader('Location', '/users')->withStatus(302);
    }

    // Если вход не удался, возвращаем на страницу входа с ошибкой и передаем введенные данные
    $flashMessages = $this->get('flash')->getMessages();
    $flashMessages['error'][] = 'Invalid email or password';
    
    return $this->get('renderer')->render($response, 'login.phtml', [
        'email' => $email,
        'flash' => $flashMessages
    ]);
});

$app->post('/logout', function (Request $request, Response $response) {
    unset($_SESSION['user']);
    return $response->withHeader('Location', '/')->withStatus(302);
});

$app->run();
