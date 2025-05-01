<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use DI\Container;
use Slim\Views\PhpRenderer;
use Page\Analyzer\Connection;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Page\Analyzer\UrlRepository;
use Page\Analyzer\UrlValidator;
use Page\Analyzer\CheckRepository;
use GuzzleHttp\Client;
use DiDom\Document;

// for what?
session_start();

// ~?
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

$app             = AppFactory::createFromContainer($container);

// ~?
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// что это, что делает
$router          = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    // зачем эти данные на index.phtml
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
            'title' => 'Ошибка 500'
        ];
        return $this->get('renderer')->render($response->withStatus(500), "500.phtml", $viewData);
    }
);

$app->post('/urls', function ($request, $response) use ($router) {
    //прнимает конекшен в конструкторе
    $urlRepository = new UrlRepository($this->get(\PDO::class));

    $url       = $request->getParsedBodyParam('url');
    $validator = new UrlValidator();
    $errors    = $validator->validate($url);

    if (count($errors) === 0) {
        $parsedUrl     = parse_url($url['name']);
        $normalizedUrl = strtolower("{$parsedUrl['scheme']}://{$parsedUrl['host']}");
        $existingUrl   = $urlRepository->findByName($url['name']);

        if ($existingUrl) {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            $params = ['id' => $existingUrl['id']];
            return $response->withRedirect($router->urlFor('url', $params));
        }

        // странно что save возвращяет id - чек php-slim-example
        $id = $urlRepository->save($normalizedUrl);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        // зачем Стринг?
        $params = ['id' => (string) $id];
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
    $urlRepository    = new UrlRepository($this->get(\PDO::class));
    $checksRepository = new CheckRepository($this->get(\PDO::class));

    $id  = $args['id'];

    $url = $urlRepository->findById((int) $id);

    if (is_null($url)) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml");
    }

    $messages = $this->get('flash')->getMessages();
    $params   = [
    'url'    => $url,
    'flash'  => $messages,
    'checks' => $checksRepository->getEntities($args['id']),
    'title'  => 'Сайт: ' . $url['name']
    ];

    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $urlRepository    = new UrlRepository($this->get(\PDO::class));
    $checksRepository = new CheckRepository($this->get(\PDO::class));

    $urls               = $urlRepository->getEntities();
    $urlsWithLastChecks = $checksRepository->getLastCheck($urls);

    /*
    $collection = collect([
    ['product_id' => 'prod-100', 'name' => 'Desk']
    ]);
    $keyed = $collection->keyBy('product_id');
    $keyed->all();
    [
        'prod-100' => ['product_id' => 'prod-100', 'name' => 'Desk']
    ]
    */
    $checks             = collect($urlsWithLastChecks)->keyBy('id');
    $urlsWithLastChecks = collect($urls)->map(
        function ($url) use ($checks) {
            $id     = $url['id'];
            $result = isset($checks[$id]) ? array_merge($url, $checks[$id]) : $url;
            return $result;
        }
    )->toArray();

    $params = [
    'urls'         => $urlsWithLastChecks,
    'title'        => 'Список сайтов',
    'currentRoute' => 'urls'
    ];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');

$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($router) {
    $urlRepository   = new UrlRepository($this->get(\PDO::class));
    $checkRepository = new CheckRepository($this->get(\PDO::class));
    // $this->get(CheckRepository::class);

    $id     = (int) $args['url_id'];
    $client = new Client();
    $url    = $urlRepository->findById($id);

    try {
        // делаем get запрос к url и получаем ответ
        $urlName    = $client->get($url["name"]);
        // получаем код из ответа
        $statusCode = $urlName->getStatusCode();
        // получаем тело ответа
        $body       = (string) $urlName->getBody();

        // html парсер
        $document       = new Document($body);

        // $document->first('h1') может быть null, но благодря optional, при этом не возвращяется ошибка
        // text() возвращяет строку
        $h1             = optional($document->first('h1'))->text() ?? "";
        $normalizedH1   = mb_strlen($h1) > 255 ? mb_strimwidth($h1, 0, 252, "...") : $h1;

        $title          = optional($document->first('title'))->text() ?? "";

        $descriptionTag = $document->first('meta[name=description]') ?? "";
        // Getting value of an attribute
        $description    = $descriptionTag ? $descriptionTag->getAttribute('content') : "";

        $checkRepository->save($id, $statusCode, $normalizedH1, $title, $description);

        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
    }

    // зачем (string)?
    $params = ['id' => (string) $id];
    return $response->withRedirect($router->urlFor('url', $params));
})->setName('url_check');

$app->run();
