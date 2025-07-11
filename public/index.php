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
use Page\Analyzer\Connection;
use Page\Analyzer\TablesInitializer;
use Page\Analyzer\UrlValidator;
use Page\Analyzer\UrlRepository;
use Page\Analyzer\CheckRepository;

/**
 * for Slim\Flash\Messages
 */
session_start();

/**
 * Dotenv class for working with environment variables
 * createImmutable() creates an instance of the class with a directory for searching
 * safeload() loads environment variables from the .env file into $_ENV and $_SERVER without overwriting existing ones
 */
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeload();
$dotenv->required(['DATABASE_URL'])->notEmpty();

/**
 * DI container in which the required dependencies are seted
 */
$container = new Container();
$container->set('renderer', function () {
    /**
     * PhpRenderer, when it renders a template, such as index.phtml, it captures the output of that template, stores it
     * in the $content variable (implicitly declared) and inserts that output into layout.phtml
     */
    $render = new PhpRenderer(__DIR__ . '/../templates');
    $render->setLayout('layout.phtml');
    return $render;
});

// Регистрируем в контейнере, что flash будет реализован через \Slim\Flash\Messages
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $connection = new Connection();
    return $connection->get();
});

/**
 * DI container has access to connection - PDO object
 * Create an instance of TablesInitializer class from DI container, which passes this connection to the class.
 *
 * in DI container pass an exemplar of any class that needs dependencies stored in the container
 */
$container->get(TablesInitializer::class);

$app = AppFactory::createFromContainer($container);

/**
 * adds Middleware for error handling. Middleware - functions that process requests and responses.
 */
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

/**
 * get an instance of route parser from the application's route collector.
 * Route parser to generate URLs based on named routes
 * Generate URL /urls/1 from route url:
 * @example
 * $response->withRedirect($router->urlFor('url', ['id' => 1]))
 */
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    /**
     * for use in layout.phtml
     */
    $viewData = [
        'title'        => 'Анализатор страниц',
        'currentRoute' => 'home'
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $viewData);
})->setName('home');

/**
 * Middlewares that handles receiving 404 and 500 status codes in the response
 */
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

    /**
     * in $url an associative array with the name key obtained from the form
     * allows accessing the entered URL via $url['name']
     */
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

        /**
         * keyBy('id') making @param field the Key and the collection its Value
         * @example
         * $checks = collect([
         *     ['id' => 1, 'status_code' => 200, latest_check => '2025-05-01 16:00:00']
         * ])->keyBy('id')->all();
         * [
         *     1 => ['id' => 1, 'status_code' => 200, latest_check => '2025-05-01 16:00:00']
         * ]
         */
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

    $id     = (int) $args['url_id'];
    $url    = $urlRepository->findById($id);

    try {
        /**
         * make a GET request with GuzzleHttp Client, by Url and @return response
         */
        $client = new Client();
        $urlName    = $client->get($url["name"]);
        $statusCode = $urlName->getStatusCode();
        $body       = (string) $urlName->getBody();

        /**
         * DiDom Document - HTML parser
         *
         * optional() does't @return error if value ('h1', 'title') is null
         *
         * text() @return string (content)
         *
         * getAttribute() @return string (attribute content)
         */
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
