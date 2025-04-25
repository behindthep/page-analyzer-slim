<?php

namespace Page\Analyzer;

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();

$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

// приложение объектом класса Slim\App
// $app = AppFactory::create();

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    //  путь  относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim  контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/courses', function ($request, $response) use ($courses) {
    $params = [
        'courses' => $courses
    ];
    return $this->get('renderer')->render($response, 'courses/index.phtml', $params);
});

$app->post('/users', function ($request, $response) {
    $name = 'page';
    $defaultValue = 1;
    $page = $request->getQueryParam($name, $defaultValue);
    $per = $request->getQueryParam('per', 10);
    return $response;
});

$app->get('/courses/{name}', function ($request, $response, array $args) use ($courses) {
    $slug = $args['name'];
    $course = $courses[$slug];
    return $response->write("<h1>{$course->name}</h1>")
      ->write("<div>{$course->body}</div>");
});

$app->run();
