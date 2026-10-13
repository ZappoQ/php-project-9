<?php

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Valitron\Validator;
use Carbon\Carbon;

// Главная страница
$app->get('/', function (Request $request, Response $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html.twig');
})->setName('home');

// Список всех URL
$app->get('/urls', function (Request $request, Response $response) {
    $pdo = Database::getConnection();
    $stmt = $pdo->query('SELECT * FROM urls ORDER BY created_at DESC');
    $urls = $stmt->fetchAll();

    $view = Twig::fromRequest($request);
    return $view->render($response, 'urls/index.html.twig', [
        'urls' => $urls,
    ]);
})->setName('urls.index');

// Конкретный URL
$app->get('/urls/{id}', function (Request $request, Response $response, array $args) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $url = $stmt->fetch();

    if (!$url) {
        return $response->withStatus(404);
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'urls/show.html.twig', [
        'url' => $url,
    ]);
})->setName('urls.show');

// Добавление URL (POST)
$app->post('/urls', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $url = trim($data['url'] ?? '');

    // Валидация
    $validator = new Validator(['url' => $url]);
    $validator->rule('required', 'url')->message('URL не должен быть пустым');
    $validator->rule('lengthMax', 'url', 255)->message('URL превышает 255 символов');
    $validator->rule('url', 'url')->message('Некорректный URL');

    $flash = $this->get(\Slim\Flash\Messages::class);

    if (!$validator->validate()) {
        $errors = $validator->errors();
        $flash->addMessage('error', array_values($errors)[0][0]);
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    // Нормализация URL
    $parsed = parse_url($url);
    $normalizedUrl = $parsed['scheme'] . '://' . $parsed['host'];

    $pdo = Database::getConnection();

    // Проверка на существование
    $stmt = $pdo->prepare('SELECT id FROM urls WHERE name = :name');
    $stmt->execute(['name' => $normalizedUrl]);
    $existing = $stmt->fetch();

    if ($existing) {
        $flash->addMessage('error', 'Страница уже существует');
        return $response->withHeader('Location', '/urls/' . $existing['id'])->withStatus(302);
    }

    // Сохранение
    $stmt = $pdo->prepare('INSERT INTO urls (name, created_at) VALUES (:name, :created_at) RETURNING id');
    $stmt->execute([
        'name' => $normalizedUrl,
        'created_at' => Carbon::now()->toDateTimeString(),
    ]);
    $id = $stmt->fetchColumn();

    $flash->addMessage('success', 'Страница успешно добавлена');
    return $response->withHeader('Location', '/urls/' . $id)->withStatus(302);
})->setName('urls.store');
