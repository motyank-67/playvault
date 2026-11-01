<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path' => '/',
    ]);
    session_start();
}

const STATUSES = ['backlog', 'playing', 'completed'];

function current_user_id(): ?int
{
    $id = (int) ($_SESSION['user_id'] ?? 0);
    return $id > 0 ? $id : null;
}

function require_user_id(): int
{
    $id = current_user_id();
    if ($id === null) {
        header('Location: /auth.php', true, 303);
        exit;
    }
    return $id;
}

function sign_in(int $id, string $username): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    unset($_SESSION['csrf']);
}

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $configPath = dirname(__DIR__) . '/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Не найден config.php. Скопируй config.example.php в config.php.');
    }
    $config = require $configPath;
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $config['host'],
        (int) $config['port'],
        $config['database'],
    );

    return $connection = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Форма устарела. Обнови страницу и попробуй снова.');
    }
}

function allowed_value(mixed $value, array $allowed, string $fallback): string
{
    $text = (string) $value;
    return in_array($text, $allowed, true) ? $text : $fallback;
}

function string_value(mixed $value, int $maxBytes): string
{
    return substr(trim((string) $value), 0, $maxBytes);
}

function integer_value(mixed $value, int $min, int $max): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false) {
        return $min;
    }
    return max($min, min($max, $number));
}

function game_from_post(): array
{
    $title = string_value($_POST['title'] ?? '', 120);
    if ($title === '') {
        throw new InvalidArgumentException('Введи название игры.');
    }

    $yearInput = trim((string) ($_POST['release_year'] ?? ''));
    $releaseYear = null;
    if ($yearInput !== '') {
        $releaseYear = filter_var($yearInput, FILTER_VALIDATE_INT);
        if ($releaseYear === false || $releaseYear < 1970 || $releaseYear > 2100) {
            throw new InvalidArgumentException('Укажи год выпуска от 1970 до 2100.');
        }
    }

    return [
        'title' => $title,
        'genre_id' => positive_field_id('genre_id'),
        'platform_id' => positive_field_id('platform_id'),
        'release_year' => $releaseYear,
        'status' => allowed_value($_POST['status'] ?? '', STATUSES, 'backlog'),
        'rating' => integer_value($_POST['rating'] ?? 0, 0, 10),
        'hours_played' => integer_value($_POST['hours_played'] ?? 0, 0, 9999),
        'note' => string_value($_POST['note'] ?? '', 500),
    ];
}

function positive_field_id(string $field): int
{
    $id = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        throw new InvalidArgumentException('Некорректный идентификатор: ' . $field . '.');
    }
    return $id;
}

function positive_id(): int
{
    return positive_field_id('id');
}

function catalog_game_id(PDO $pdo, string $title, int $genreId, ?int $releaseYear): int
{
    $statement = $pdo->prepare(
        'INSERT INTO games (title, genre_id, release_year)
         VALUES (:title, :genre_id, :release_year)
         ON CONFLICT (title, genre_id)
         DO UPDATE SET title = EXCLUDED.title
         RETURNING id'
    );
    $statement->execute([
        'title' => $title,
        'genre_id' => $genreId,
        'release_year' => $releaseYear,
    ]);
    return (int) $statement->fetchColumn();
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function redirect_home(): never
{
    header('Location: /', true, 303);
    exit;
}
