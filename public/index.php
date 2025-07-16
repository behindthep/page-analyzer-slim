<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use DI\Container;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use DiDom\Document;
use Page\Analyzer\Connections\ConnectionInterface;
use Database\Factories\ConnectionFactory;
use Page\Analyzer\{
    UrlValidator,
    UrlRepository,
    CheckRepository,
    TablesInitializer
};


session_start();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeload();
$dotenv->required(['DATABASE_URL'])->notEmpty();

$container = new Container();

$container->set('renderer', function () {
    $render = new PhpRenderer(__DIR__ . '/../templates');
    $render->setLayout('layout.phtml');
    return $render;
});

// Регистрируем в контейнере через что (\Slim\Flash\Messages) будет реализовываться конкретный объект (flash)
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(ConnectionInterface::class, function () {
    $dbType = $_ENV['DB_TYPE'] ?? 'sqlite';
    return ConnectionFactory::create($dbType);
});

$container->set(\PDO::class, function ($c) {
    return $c->get(ConnectionInterface::class)->get();
});

$container->get(TablesInitializer::class);

$app = AppFactory::createFromContainer($container);

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $viewData = [
        'title'        => 'Анализатор страниц',
        'currentRoute' => 'home'
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $viewData);
})->setName('home');


$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails) {
    $response = new \Slim\Psr7\Response();
    $viewData = [
        'title' => 'Страница не найдена'
    ];
    return $this->get('renderer')->render($response->withStatus(404), "404.phtml", $viewData);
});

$errorMiddleware->setErrorHandler(
    HttpMethodNotAllowedException::class,
    function ($request, $exception, $displayErrorDetails) {
        $response = new \Slim\Psr7\Response();
        $viewData = [
            'title' => 'Недопустимое действие'
        ];
        return $this->get('renderer')->render($response->withStatus(405), "405.phtml", $viewData);
    }
);


$app->post('/urls', function ($request, $response) use ($router) {
    $urlRepository = $this->get(UrlRepository::class);

    $url       = $request->getParsedBodyParam('url');
    $validator = new UrlValidator();
    $errors    = $validator->validate($url);

    if (count($errors) === 0) {
        $parsedUrl     = parse_url($url['name']);
        $normalizedUrl = strtolower("{$parsedUrl['scheme']}://{$parsedUrl['host']}");
        $existingUrl   = $urlRepository->findByName($normalizedUrl);

        if ($existingUrl) {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            $params = [
                'id' => $existingUrl['id']
            ];
            return $response->withRedirect($router->urlFor('url', $params));
        }

        $id = $urlRepository->save($normalizedUrl);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        $params = [
            'id' => (string) $id
        ];
        return $response->withRedirect($router->urlFor('url', $params));
    }

    $params = [
        'url'    => $url,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('url_store');


$app->get('/urls/{id:[0-9]+}', function ($request, $response, $args) {
    $urlRepository   = $this->get(UrlRepository::class);
    $checkRepository = $this->get(CheckRepository::class);

    $id  = $args['id'];

    $url = $urlRepository->findById((int) $id);

    if (is_null($url)) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml");
    }

    $messages = $this->get('flash')->getMessages();
    $params   = [
        'url'    => $url,
        'flash'  => $messages,
        'checks' => $checkRepository->getEntities($args['id']),
        'title'  => 'Сайт: ' . $url['name']
    ];
    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');


$app->get('/urls', function ($request, $response) {
    $urlRepository   = $this->get(UrlRepository::class);
    $checkRepository = $this->get(CheckRepository::class);

    $urls               = $urlRepository->getEntities();
    $urlsWithLastChecks = [];

    if (!empty($urls)) {
        $urlsWithLastChecks = $checkRepository->getLastCheck($urls);

        $checks             = collect($urlsWithLastChecks)->keyBy('id');
        $urlsWithLastChecks = collect($urls)->map(function ($url) use ($checks) {
            $id     = $url['id'];
            $result = isset($checks[$id]) ? array_merge($url, $checks[$id]) : $url;
            return $result;
        })->toArray();
    }

    $params = [
        'urls'         => $urlsWithLastChecks,
        'title'        => 'Список сайтов',
        'currentRoute' => 'urls'
    ];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');


$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($router) {
    $urlRepository   = $this->get(UrlRepository::class);
    $checkRepository = $this->get(CheckRepository::class);

    $id  = (int) $args['url_id'];
    $url = $urlRepository->findById($id);

    try {
        $client     = new Client();
        $urlName    = $client->get($url["name"]);
        $statusCode = $urlName->getStatusCode();
        $body       = (string) $urlName->getBody();

        $document       = new Document($body);
        $h1             = optional($document->first('h1'))->text() ?? "";
        $normalizedH1   = mb_strlen($h1) > 255 ? mb_strimwidth($h1, 0, 252, "...") : $h1;
        $title          = optional($document->first('title'))->text() ?? "";
        $descriptionTag = $document->first('meta[name=description]') ?? "";
        $description    = $descriptionTag ? $descriptionTag->getAttribute('content') : "";

        $checkRepository->save($id, $statusCode, $normalizedH1, $title, $description);

        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
    }

    $params = [
        'id' => (string) $id
    ];
    return $response->withRedirect($router->urlFor('url', $params));
})->setName('url_check');


$app->run();
