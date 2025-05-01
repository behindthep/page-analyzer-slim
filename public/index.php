<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Views\PhpRenderer;
use Dotenv\Dotenv;
use Page\Analyzer\Connection;
use Page\Analyzer\UrlRepository;
use Page\Analyzer\CheckRepository;
use Page\Analyzer\UrlValidator;
use GuzzleHttp\Client;
use DiDom\Document;

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

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $connection = new Connection();
    return $connection->get();
});

$app = AppFactory::createFromContainer($container);
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $viewData = [
        'title' => 'Анализатор страниц',
        'currentRoute' => 'home'
    ];

    return $this->get('renderer')->render($response, 'index.phtml', $viewData);
})->setName('home');

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails) {
    $response = new \Slim\Psr7\Response();
    $viewData = [
        'title' => 'Страница не найдена!'
    ];

    return $this->get('renderer')->render($response->withStatus(404), "404.phtml", $viewData);
});

$errorMiddleware->setErrorHandler(
    HttpMethodNotAllowedException::class,
    function ($request, $exception, $displayErrorDetails) {
        $response = new \Slim\Psr7\Response();
        $viewData = [
            'title' => 'Ошибка 500!'
        ];

        return $this->get('renderer')->render($response->withStatus(500), "500.phtml", $viewData);
    }
);

$app->get('/urls/{id:[0-9]+}', function ($request, $response, $args) {
    $urlRepository = $this->get(UrlRepository::class);
    $checksRepo = new CheckRepository($this->get(\PDO::class));

    $id = $args['id'];

    $url = $urlRepository->find((int) $id);

    if (is_null($url)) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml",);
    }

    $messages = $this->get('flash')->getMessages();
    $params = [
        'url' => $url,
        'flash' => $messages,
        'checks' => $checksRepo->getEntities($args['id']),
        'title' => 'Сайт: ' . $url['name']
    ];

    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $urlRepository = $this->get(UrlRepository::class);
    $checksRepo = new CheckRepository($this->get(\PDO::class));
    $urls = $urlRepository->getEntities();
    $urlsWithLastChecks = $checksRepo->getLastCheck($urls);

    $checksCollection = collect($urlsWithLastChecks)->keyBy('id');
    $mergedIdWithLastChecks = collect($urls)->map(function ($url) use ($checksCollection) {
        $id = $url['id'];
        $result = isset($checksCollection[$id]) ? array_merge($url, $checksCollection[$id]) : $url;
        return $result;
    })->toArray();

    $params = [
      'urls' => $mergedIdWithLastChecks,
      'title' => 'Список сайтов',
      'currentRoute' => 'urls'
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
        $urlName = $client->get($url["name"]);
        $statusCode = $urlName->getStatusCode();
        $body = (string) $urlName->getBody();

        $document = new Document($body);
        $h1 = optional($document->first('h1'))->text() ?? "";
        $h1 = mb_strlen($h1) > 255 ? mb_strimwidth($h1, 0, 252, "...") : $h1;
        $title = optional($document->first('title'))->text() ?? "";
        $descriptionTag = $document->first('meta[name=description]') ?? "";
        $description = $descriptionTag ? $descriptionTag->getAttribute('content') : "";
        $checkRepository->addCheck($urlId, $statusCode, $h1, $title, $description);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
    }

    $params = ['id' => (string) $urlId];
    return $response->withRedirect($router->urlFor('url', $params));
})->setName('url_check');

$app->run();
