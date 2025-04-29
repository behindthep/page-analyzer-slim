<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use Page\Analyzer\Car;
use Page\Analyzer\CarRepository;
use Page\Analyzer\CarValidator;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;

session_start();

const ADMIN_EMAIL = 'admin@project.io';

$container = new Container();

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $conn = new \PDO('sqlite:database.sqlite');
    // $conn = new \PDO('localhost', 'page-analyzer', 'alex', 1111);
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});

$initFilePath = implode('/', [dirname(__DIR__), 'init.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

$app = AppFactory::createFromContainer($container);

$router = $app->getRouteCollector()->getRouteParser();

$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

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
$app->add($afterMiddleware);
// $app->add($beforeMiddleware);
$app->add($logMiddleware);

$app->get('/', function ($request, $response) use ($router) {
    if (isset($_SESSION['isAdmin'])) {
        return $response->withRedirect($router->urlFor('cars.index'));
    }

    $messages = $this->get('flash')->getMessages();
    $params = [
        'email' => '',
        'flash' => $messages ?? []
    ];

    return $this->get('renderer')->render($response, 'home.phtml', $params);
})->setName('home');

$app->post('/login', function ($request, $response) use ($router) {
    $email = $request->getParsedBodyParam('email');

    if ($email === ADMIN_EMAIL) {
        $_SESSION['isAdmin'] = true;

        return $response->withRedirect($router->urlFor('cars.index'));
    }

    $this->get('flash')->addMessage('error', 'Access Denied!');

    return $response->withRedirect($router->urlFor('home'));
});

$app->delete('/logout', function ($request, $response) use ($router) {
        session_destroy();

        return $response->withRedirect($router->urlFor('home'));
});

$app->get('/cars', function ($request, $response) use ($router) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $term = $request->getQueryParam('term') ?? '';
    // запросить объект репозитория из контейнера
    $carRepository = $this->get(CarRepository::class);
    // контейнер видит, CarRepository нуждается в PDO и создает экземпляр, передав ему соединение
    $cars = $carRepository->getEntities();

    $carsList = isset($term) ? array_filter($cars, fn($car) => str_contains($car->getMake(), $term) !== false) : $cars;

    $messages = $this->get('flash')->getMessages();

    $params = [
      'cars' => $carsList,
      'term' => $term,
      'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/index.phtml', $params);
})->setName('cars.index');

$app->post('/cars', function ($request, $response) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $carData = $request->getParsedBodyParam('car');

    $validator = new CarValidator();
    $errors = $validator->validate($carData);

    if (count($errors) === 0) {
        $car = Car::fromArray([$carData['make'], $carData['model']]);
        $carRepository->save($car);
        $this->get('flash')->addMessage('success', 'Car was added successfully');
        return $response->withRedirect($router->urlFor('cars.index'));
    }

    $params = [
        'car' => $carData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'cars/new.phtml', $params);
})->setName('cars.store');

$app->get('/cars/new', function ($request, $response) use ($router) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $params = [
        'car' => new Car(),
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'cars/new.phtml', $params);
})->setName('cars.create');

$app->get('/cars/{id}', function ($request, $response, $args) use ($router) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];
    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'car' => $car,
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/show.phtml', $params);
})->setName('cars.show');

$app->get('/cars/{id}/edit', function ($request, $response, $args) use ($router) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $carRepository = $this->get(CarRepository::class);
    $messages = $this->get('flash')->getMessages();
    $id = $args['id'];
    $car = $carRepository->find($id);

    $params = [
        'car' => $car,
        'errors' => [],
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/edit.phtml', $params);
})->setName('cars.edit');

$app->patch('/cars/{id}', function ($request, $response, $args) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];

    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $carData = $request->getParsedBodyParam('car');
    $validator = new CarValidator();
    $errors = $validator->validate($carData);

    if (count($errors) === 0) {
        $car->setMake($carData['make']);
        $car->setModel($carData['model']);
        $carRepository->save($car);
        $this->get('flash')->addMessage('success', "Car was updated successfully");
        return $response->withRedirect($router->urlFor('cars.show', $args));
    }

    $params = [
        'car' => $car,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'cars/edit.phtml', $params);
});

$app->delete('/cars/{id}', function ($request, $response, $args) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];

    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $carRepository->delete($car);

    $this->get('flash')->addMessage('success', "Car was deleted successfully");

    return $response->withRedirect($router->urlFor('cars.index', $args));
});

$app->run();
