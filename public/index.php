<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Exception\HttpNotFoundException;
use Dotenv\Dotenv;
use Page\Analyzer\Connection;
use Page\Analyzer\UrlRepository;
use Page\Analyzer\CheckRepository;
use Page\Analyzer\UrlValidator;
use GuzzleHttp\Client;
use DiDom\Document;
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeload();
$dotenv->required(['DATABASE_URL'])->notEmpty();

$container = new Container();

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $connection = new Connection();
    return $connection->get();
});

$initFilePath = implode('/', [dirname(__DIR__), 'init.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

$app = AppFactory::createFromContainer($container);

$router = $app->getRouteCollector()->getRouteParser();

$app->addErrorMiddleware(true, true, true);
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
})->setName('home');

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails) {
    $response = new \Slim\Psr7\Response();
    return $this->get('renderer')->render($response->withStatus(404), "404.phtml");
});

$app->get('/urls/{id}', function ($request, $response, $args) {
    $urlRepository = $this->get(UrlRepository::class);
    $checksRepo = new CheckRepository($this->get(\PDO::class));
    $id = $args['id'];

    if (!is_numeric($id)) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml",);
    }

    $url = $urlRepository->find((int) $id);

    if (is_null($url)) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml",);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'url' => $url,
        'flash' => $messages,
        'checks' => $checksRepo->getEntities($args['id'])
    ];

    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    // запросить объект репозитория из контейнера
    $urlRepository = $this->get(UrlRepository::class);
    // контейнер видит, urlRepository нуждается в PDO и создает экземпляр, передав ему соединение
    $checksRepo = new CheckRepository($this->get(\PDO::class));

    $urls = $urlRepository->getEntities();

    $urlsWithLastChecks = array_map(function ($url) use ($checksRepo) {
        $lastCheck = $checksRepo->getLastCheck($url['id']);
        $url['data'] = [
            'last_check' => $lastCheck['created_at'] ?? '',
            'status_code' => $lastCheck['status_code'] ?? ''
        ];
        return $url;
    }, $urls);

    $params = [
      'urls' => $urlsWithLastChecks
    ];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');

$app->post('/urls', function ($request, $response) use ($router) {
    $urlRepository = $this->get(UrlRepository::class);
    $urlData = $request->getParsedBodyParam('url');

    $validator = new UrlValidator();
    $errors = $validator->validate($urlData);

    if (count($errors) === 0) {

        $parsedUrl = parse_url($urlData['name']);
        $normalizedUrl = strtolower("{$parsedUrl['scheme']}://{$parsedUrl['host']}");

        $existingUrl = $urlRepository->findByName($urlData['name']);

        if ($existingUrl) {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            $params = ['id' => $existingUrl['id']];

            return $response->withRedirect($router->urlFor('url', $params));
        }

        $newUrlId = $urlRepository->save($$normalizedUrl);

        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');

        $params = ['id' => (string) $newUrlId];

        return $response->withRedirect($router->urlFor('url', $params));
    }

    $params = [
        'url' => $urlData,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
})->setName('url_store');

$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($router) {
    $urlId = (int) $args['url_id'];

    $urlRepository = $this->get(UrlRepository::class);
    $checkRepository = $this->get(CheckRepository::class);
    $client = new Client();

    $url = $urlRepository->find($urlId);

    try {
        $urlName = $client->get($url['name']);
        $statusCode = $urlName->getStatusCode();
        $body = (string) $urlName->getBody();

        $document = new Document($body);
        $h1 = optional($document->first('h1'))->text() ?? null;
        $title = optional($document->first('title'))->text() ?? null;
        $descriptionTag = $document->first('meta[name=description]') ?? null;
        $description = $descriptionTag ? $descriptionTag->getAttribute('content') : null;
        $checkRepository->addCheck($urlId, $statusCode, $h1, $title, $description);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
    }

    $params = ['id' => (string) $urlId];

    return $response->withRedirect($router->urlFor('url',  $params));
})->setName('url_check');

$app->run();
