<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

$mode = ($_POST['mode'] ?? $_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $pdo = db();
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($mode === 'register') {
            if (!preg_match('/^[a-z0-9_]{3,32}$/D', $username)) {
                throw new InvalidArgumentException('Логин: 3–32 символа, латинские буквы, цифры или _.');
            }
            if (strlen($password) < 8 || strlen($password) > 72) {
                throw new InvalidArgumentException('Пароль: от 8 до 72 байт.');
            }
            if (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
                throw new InvalidArgumentException('Пароли не совпадают.');
            }

            $statement = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :hash) RETURNING id');
            $statement->execute([
                'username' => $username,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $statement->fetchColumn();

            sign_in($userId, $username);
            flash('Аккаунт создан! Это твоя личная коллекция.');
        } else {
            $statement = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
            $statement->execute(['username' => $username]);
            $user = $statement->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                throw new InvalidArgumentException('Неверный логин или пароль.');
            }
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $update->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
            }
            sign_in((int) $user['id'], $user['username']);
            flash('С возвращением!');
        }

        header('Location: /', true, 303);
        exit;
    } catch (Throwable $error) {
        $message = $error instanceof PDOException
            ? ($error->getCode() === '23505' ? 'Этот логин уже занят.' : 'Ошибка базы данных. Проверь подключение и структуру базы.')
            : $error->getMessage();
        flash($message, 'error');
        header('Location: /auth.php?mode=' . $mode, true, 303);
        exit;
    }
}

if (current_user_id() !== null) {
    header('Location: /', true, 303);
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $mode === 'register' ? 'Регистрация' : 'Вход' ?> — PlayVault</title>
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
  <main class="auth-card">
    <a class="auth-brand" href="/"><span class="brand-mark">✦</span><strong>PLAYVAULT</strong></a>
    <div class="auth-eyebrow">ТВОЯ ЛИЧНАЯ КОЛЛЕКЦИЯ</div>
    <h1><?= $mode === 'register' ? 'Создай аккаунт' : 'С возвращением!' ?></h1>
    <p class="auth-subtitle"><?= $mode === 'register'
        ? 'У каждого игрока — свои игры, оценки и прогресс.'
        : 'Войди, чтобы открыть свою игровую библиотеку.' ?></p>

    <nav class="auth-tabs" aria-label="Авторизация">
      <a class="<?= $mode === 'login' ? 'active' : '' ?>" href="/auth.php?mode=login">Войти</a>
      <a class="<?= $mode === 'register' ? 'active' : '' ?>" href="/auth.php?mode=register">Регистрация</a>
    </nav>

    <?php if ($flash): ?><div class="notice notice-<?= h($flash['type']) ?>" role="alert"><?= h($flash['message']) ?></div><?php endif; ?>

    <form class="auth-form" method="post" action="/auth.php" autocomplete="on">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="<?= h($mode) ?>">
      <label>Логин<input type="text" name="username" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9_]{3,32}" required placeholder="motya"></label>
      <label>Пароль<input type="password" name="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" minlength="8" required placeholder="Минимум 8 символов"></label>
      <?php if ($mode === 'register'): ?>
        <label>Повтори пароль<input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required placeholder="Ещё раз пароль"></label>
      <?php endif; ?>
      <button type="submit"><?= $mode === 'register' ? 'Создать аккаунт' : 'Войти в коллекцию' ?> →</button>
    </form>
    <p class="auth-footnote">Логин — латиницей. Пароли сохраняются только в виде хеша.</p>
  </main>
</body>
</html>
