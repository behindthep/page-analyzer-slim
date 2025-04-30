<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Page\Analyzer\Url;
use Page\Analyzer\UrlRepository;
use Page\Analyzer\UrlValidator;
use Page\Analyzer\Check;
use Page\Analyzer\CheckRepository;
use Page\Analyzer\SeoChecker;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;

session_start();

$container = new Container();

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $conn = new \PDO('sqlite:database.sqlite');
    // $conn = new \PDO('localhost', 'url-analyzer', 'alex', 1111);
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});

$container->set(SeoChecker::class, function () {
    return new SeoChecker();
});

$initFilePath = implode('/', [dirname(__DIR__), 'init.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

$app = AppFactory::createFromContainer($container);

$router = $app->getRouteCollector()->getRouteParser();

$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    $params = [
        'url' => new Url()
    ];

    return $this->get('renderer')->render($response, 'home.phtml', $params);
})->setName('home');

$app->get('/urls', function ($request, $response) {
    // запросить объект репозитория из контейнера
    $urlRepository = $this->get(UrlRepository::class);
    // контейнер видит, urlRepository нуждается в PDO и создает экземпляр, передав ему соединение
    $urls = $urlRepository->getEntities();

    $params = [
      'urls' => $urls
    ];

    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
})->setName('urls.index');

$app->post('/urls', function ($request, $response) use ($router) {
    $urlRepository = $this->get(UrlRepository::class);
    $urlData = $request->getParsedBodyParam('url');

    $validator = new UrlValidator();
    $errors = $validator->validate($urlData);

    if (count($errors) === 0) {
        $existingUrl = $urlRepository->findByName($urlData['name']);

        if ($existingUrl) {
            $this->get('flash')->addMessage('success', 'Url already exists');

            return $response->withRedirect($router->urlFor('urls.show', ['id' => $existingUrl->getId()]));
        }

        $url = new Url();
        $url->setName($urlData['name']);
        $urlRepository->save($url);

        $this->get('flash')->addMessage('success', 'Url was added successfully');

        return $response->withRedirect($router->urlFor('urls.show', ['id' => $url->getId()]));
    }

    $params = [
        'url' => $urlData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
})->setName('urls.store');

$app->get('/urls/{id}', function ($request, $response, $args) {
    $urlRepository = $this->get(UrlRepository::class);
    $id = $args['id'];
    $url = $urlRepository->find($id);

    if (is_null($url)) {
        return $response->write('Url not found')->withStatus(404);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'url' => $url,
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'urls/show.phtml', $params);
})->setName('urls.show');

$app->post('/checks', function ($request, $response) use ($router) {
    $checkRepository = $this->get(CheckRepository::class);
    $urlRepository = $this->get(UrlRepository::class);
    $seoChecker = $this->get(SeoChecker::class);

    $urlId = $request->getParsedBodyParam('url_id');
    $url = $urlRepository->find($urlId);

    if (is_null($url)) {
        return $response->withStatus(404)->write('URL not found');
    }

    $seoData = $seoChecker->check($url->getName());

    $check = new Check();
    $check->setResponseCode($seoData['response_code']);
    $check->setHeader($seoData['header']);
    $check->setTitle($seoData['title']);
    $check->setDescription($seoData['description']);

    $checkRepository->save($check);

    $this->get('flash')->addMessage('success', 'Page was checked successfully');

    return $response->withRedirect($router->urlFor('urls.show', ['id' => $url->getId()]));
})->setName('checks.store');

$app->run();
