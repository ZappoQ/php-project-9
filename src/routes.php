<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// Главная страница
$app->get('/', function (Request $request, Response $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html.twig', [
        'title' => 'Анализатор страниц'
    ]);
});

// Тестовый маршрут
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});
