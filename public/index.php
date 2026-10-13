<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Flash\Messages;

require __DIR__ . '/../vendor/autoload.php';

// Запускаем сессию для flash-сообщений
session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Создаём таблицы, если их нет
if (isset($_ENV['DATABASE_URL']) || isset($_SERVER['DATABASE_URL']) || getenv('DATABASE_URL')) {
    \App\Database::migrate();
}

$container = new Container();

$container->set(Messages::class, function () {
    return new Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);

// Передаём flash в Twig как глобальную переменную
$twig->getEnvironment()->addGlobal('flash', $container->get(Messages::class)->getMessages());

$app->add(TwigMiddleware::create($app, $twig));

require __DIR__ . '/../src/routes.php';

$app->run();
