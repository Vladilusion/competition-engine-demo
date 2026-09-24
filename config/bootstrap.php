<?php

declare(strict_types=1);

use CompetitionDemo\Domain\ScoringEngine;
use CompetitionDemo\Infrastructure\PdoCompetitionGateway;

require dirname(__DIR__) . '/src/autoload.php';

foreach (is_file(dirname(__DIR__) . '/.env') ? file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] ??= trim($value);
    }
}

$secure = ($_SERVER['HTTPS'] ?? '') === 'on';
session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$pdo = new PDO(
    getenv('DB_DSN') ?: ($_ENV['DB_DSN'] ?? 'mysql:host=127.0.0.1;port=3306;dbname=competition_demo;charset=utf8mb4'),
    getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'competition'),
    getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? 'competition'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
);
$gateway = new PdoCompetitionGateway($pdo, new ScoringEngine());

function env(string $key, string $default = ''): string { return getenv($key) ?: ($_ENV[$key] ?? $default); }
function e(string|int|float|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
