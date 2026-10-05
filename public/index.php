<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// Загружаем переменные окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Создаем DI-контейнер
$container = new Container();

// Создаем приложение Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// Подключаем Twig
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

// Подключаем маршруты
require __DIR__ . '/../src/routes.php';

$app->run();
