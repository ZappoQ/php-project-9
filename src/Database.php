<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $databaseUrl = $_ENV['DATABASE_URL']
                ?? $_SERVER['DATABASE_URL']
                ?? getenv('DATABASE_URL')
                ?? '';

            if (empty($databaseUrl)) {
                throw new \RuntimeException('DATABASE_URL не задан в переменных окружения');
            }

            $params = parse_url($databaseUrl);

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $params['host'],
                $params['port'] ?? 5432,
                ltrim($params['path'], '/')
            );

            try {
                self::$instance = new PDO($dsn, $params['user'], $params['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException('Ошибка подключения к БД: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Создаёт таблицу urls, если её нет.
     */
    public static function migrate(): void
    {
        $pdo = self::getConnection();

        $pdo->exec('
        CREATE TABLE IF NOT EXISTS urls (
            id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
            name VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');

        $pdo->exec('
        CREATE TABLE IF NOT EXISTS url_checks (
            id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
            url_id BIGINT NOT NULL REFERENCES urls(id) ON DELETE CASCADE,
            status_code INT,
            h1 VARCHAR(255),
            title VARCHAR(255),
            description TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_url_checks_url_id ON url_checks(url_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_url_checks_created_at ON url_checks(created_at DESC)');
    }
}
