<?php

namespace Page\Analyzer;

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();

$app->get('/', function ($request, $response) {
    return $response->write('Welcome to Slim!');
});

$app->get('/about', function ($request, $response) {
    return $response->write('About My Site');
});

$app->run();
