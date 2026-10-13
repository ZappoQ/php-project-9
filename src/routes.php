<?php

use App\Database;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Flash\Messages;
use Slim\Views\Twig;
use Valitron\Validator;

// Главная страница
$app->get('/', function (Request $request, Response $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'index.html.twig');
})->setName('home');

// Список всех URL
$app->get('/urls', function (Request $request, Response $response) {
    $pdo = Database::getConnection();

    $stmt = $pdo->query('
        SELECT
            urls.id,
            urls.name,
            urls.created_at,
            MAX(url_checks.created_at) AS last_check_at,
            (
                SELECT status_code
                FROM url_checks
                WHERE url_checks.url_id = urls.id
                ORDER BY created_at DESC
                LIMIT 1
            ) AS last_status_code
        FROM urls
        LEFT JOIN url_checks ON url_checks.url_id = urls.id
        GROUP BY urls.id
        ORDER BY urls.created_at DESC
    ');
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

    $stmt = $pdo->prepare('
        SELECT * FROM url_checks
        WHERE url_id = :url_id
        ORDER BY created_at DESC
    ');
    $stmt->execute(['url_id' => $url['id']]);
    $checks = $stmt->fetchAll();

    $view = Twig::fromRequest($request);
    return $view->render($response, 'urls/show.html.twig', [
        'url' => $url,
        'checks' => $checks,
    ]);
})->setName('urls.show');

// Добавление URL (POST)
$app->post('/urls', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $url = trim($data['url'] ?? '');

    $validator = new Validator(['url' => $url]);
    $validator->rule('required', 'url')->message('URL не должен быть пустым');
    $validator->rule('lengthMax', 'url', 255)->message('URL превышает 255 символов');
    $validator->rule('url', 'url')->message('Некорректный URL');

    /** @var Messages $flash */
    $flash = $this->get(Messages::class);

    if (!$validator->validate()) {
        $errors = $validator->errors();
        $flash->addMessage('error', array_values($errors)[0][0]);
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    $parsed = parse_url($url);
    $normalizedUrl = $parsed['scheme'] . '://' . $parsed['host'];

    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('SELECT id FROM urls WHERE name = :name');
    $stmt->execute(['name' => $normalizedUrl]);
    $existing = $stmt->fetch();

    if ($existing) {
        $flash->addMessage('error', 'Страница уже существует');
        return $response->withHeader('Location', '/urls/' . $existing['id'])->withStatus(302);
    }

    $stmt = $pdo->prepare('
        INSERT INTO urls (name, created_at)
        VALUES (:name, :created_at)
        RETURNING id
    ');
    $stmt->execute([
        'name' => $normalizedUrl,
        'created_at' => Carbon::now()->toDateTimeString(),
    ]);
    $id = $stmt->fetchColumn();

    $flash->addMessage('success', 'Страница успешно добавлена');
    return $response->withHeader('Location', '/urls/' . $id)->withStatus(302);
})->setName('urls.store');

// Создание проверки
$app->post('/urls/{url_id}/checks', function (Request $request, Response $response, array $args) {
    $urlId = (int) $args['url_id'];
    $pdo = Database::getConnection();

    // Проверяем, что URL существует
    $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
    $stmt->execute(['id' => $urlId]);
    $url = $stmt->fetch();

    if (!$url) {
        return $response->withStatus(404);
    }

    /** @var Messages $flash */
    $flash = $this->get(Messages::class);

    try {
        // Создаём HTTP-клиент
        $client = new \GuzzleHttp\Client([
            'timeout' => 10,
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => false,
            ],
            'http_errors' => false,
        ]);

        // Выполняем запрос
        $res = $client->request('GET', $url['name']);
        $statusCode = $res->getStatusCode();
    } catch (\GuzzleHttp\Exception\ConnectException $e) {
        // Сетевая ошибка (сайт недоступен)
        $flash->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
        return $response->withHeader('Location', '/urls/' . $urlId)->withStatus(302);
    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        // Прочие ошибки Guzzle
        $flash->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
        return $response->withHeader('Location', '/urls/' . $urlId)->withStatus(302);
    }

    // Сохраняем проверку
    $stmt = $pdo->prepare('
        INSERT INTO url_checks (url_id, status_code, created_at)
        VALUES (:url_id, :status_code, :created_at)
    ');
    $stmt->execute([
        'url_id' => $urlId,
        'status_code' => $statusCode,
        'created_at' => Carbon::now()->toDateTimeString(),
    ]);

    $flash->addMessage('success', 'Страница успешно проверена');
    return $response->withHeader('Location', '/urls/' . $urlId)->withStatus(302);
})->setName('urls.checks.store');
