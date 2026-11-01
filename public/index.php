<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/ui.php';
$userId = require_user_id();
$username = (string) ($_SESSION['username'] ?? 'Игрок');
$initial = strtoupper($username[0] ?? 'И');

$q = string_value($_GET['q'] ?? '', 120);
$status = allowed_value($_GET['status'] ?? '', [...STATUSES, 'all', 'favorites'], 'all');
$sort = allowed_value($_GET['sort'] ?? '', ['recent', 'rating', 'title'], 'recent');

try {
    $pdo = db();

    $genres = $pdo->query("SELECT id, name FROM genres ORDER BY (name <> 'RPG'), name")->fetchAll();
    $platforms = $pdo->query("SELECT id, name FROM platforms ORDER BY (name <> 'PC'), name")->fetchAll();
    $genre = allowed_value($_GET['genre'] ?? '', [...array_column($genres, 'name'), 'all'], 'all');

    $statsStatement = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'playing') AS playing,
                COUNT(*) FILTER (WHERE status = 'completed') AS completed,
                COUNT(*) FILTER (WHERE status = 'backlog') AS backlog,
                COUNT(*) FILTER (WHERE favorite = TRUE) AS favorites,
                COALESCE(SUM(hours_played), 0) AS total_hours,
                ROUND(AVG(NULLIF(rating, 0))::numeric, 1) AS avg_rating
         FROM collection_items WHERE user_id = :user_id"
    );
    $statsStatement->execute(['user_id' => $userId]);
    $stats = $statsStatement->fetch();

    $conditions = ['ci.user_id = :user_id'];
    $parameters = ['user_id' => $userId];
    if ($q !== '') {
        $conditions[] = "CONCAT_WS(' ', g.title, p.name, gr.name, ci.note) ILIKE :search";
        $parameters['search'] = '%' . $q . '%';
    }
    if ($status === 'favorites') {
        $conditions[] = 'ci.favorite = TRUE';
    } elseif ($status !== 'all') {
        $conditions[] = 'ci.status = :status';
        $parameters['status'] = $status;
    }
    if ($genre !== 'all') {
        $conditions[] = 'gr.name = :genre';
        $parameters['genre'] = $genre;
    }

    $sortSql = match ($sort) {
        'rating' => 'ci.rating DESC, ci.added_at DESC',
        'title' => 'LOWER(g.title) ASC',
        default => 'ci.added_at DESC, ci.id DESC',
    };
    $sql = 'SELECT ci.id, ci.game_id, ci.platform_id, ci.status, ci.rating,
                   ci.hours_played, ci.favorite, ci.note, ci.added_at,
                   g.title, g.genre_id, g.release_year,
                   gr.name AS genre, p.name AS platform
            FROM collection_items AS ci
            JOIN games AS g ON g.id = ci.game_id
            JOIN genres AS gr ON gr.id = g.genre_id
            JOIN platforms AS p ON p.id = ci.platform_id';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY ' . $sortSql;
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $games = $statement->fetchAll();
} catch (Throwable $error) {
    http_response_code(503);
    ?>
    <!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PlayVault — настройка</title><link rel="stylesheet" href="/assets/style.css"></head>
    <body class="setup-body"><main class="setup-card">
      <span class="brand-mark"><?= icon('gamepad', 24) ?></span>
      <h1>Нужно подключить PostgreSQL</h1>
      <p>Сайт уже работает на PHP, но не может открыть базу данных.</p>
      <ol><li>Создай базу <code>playvault</code>.</li><li>Выполни <code>database/schema.sql</code>.</li><li>Скопируй <code>config.example.php</code> в <code>config.php</code> и проверь имя пользователя PostgreSQL.</li></ol>
      <p class="setup-foot">Подробные команды — в README.md. Техническая причина: <?= h($error instanceof PDOException ? 'нет подключения к PostgreSQL или таблицам базы данных' : $error->getMessage()) ?></p>
    </main></body></html>
    <?php
    exit;
}

$total = (int) $stats['total'];
$playing = (int) $stats['playing'];
$completed = (int) $stats['completed'];
$favorites = (int) $stats['favorites'];
$totalHours = (int) $stats['total_hours'];
$progress = $total ? (int) round($completed * 100 / $total) : 0;
$average = $stats['avg_rating'] === null ? '—' : $stats['avg_rating'];
$themes = ['RPG' => 'cosmic', 'Action' => 'ember', 'Adventure' => 'forest', 'Shooter' => 'cobalt', 'Strategy' => 'gold', 'Indie' => 'blossom', 'Other' => 'graphite'];
$statusLabels = ['backlog' => 'В планах', 'playing' => 'Играю сейчас', 'completed' => 'Пройдено'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#202338">
  <meta name="description" content="PlayVault — личная коллекция видеоигр на PHP и PostgreSQL">
  <title>PlayVault — твоя игровая коллекция</title>
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/style.css">
  <script src="/assets/app.js" defer></script>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <a class="brand" href="/"><span class="brand-mark"><?= icon('gamepad',22) ?></span><span><strong>PLAYVAULT</strong><small>GAME COLLECTION</small></span></a>
    <div class="side-label">ПРОСТРАНСТВО</div>
    <nav class="side-nav" aria-label="Навигация">
      <a class="active" href="/"><?= icon('layers') ?> Обзор <span>⌘</span></a>
      <a href="/#collection"><?= icon('gamepad') ?> Моя коллекция <span><?= $total ?></span></a>
      <a href="/?status=favorites#collection"><?= icon('heart') ?> Избранное <span><?= $favorites ?></span></a>
      <a href="/#stats"><?= icon('trophy') ?> Статистика</a>
    </nav>
    <div class="side-label second">БЫСТРЫЕ ФИЛЬТРЫ</div>
    <nav class="side-nav filters" aria-label="Статусы">
      <a href="/?status=playing#collection"><span class="sidebar-dot green"></span> Сейчас играю <span><?= $playing ?></span></a>
      <a href="/?status=backlog#collection"><span class="sidebar-dot violet"></span> В планах <span><?= (int) $stats['backlog'] ?></span></a>
      <a href="/?status=completed#collection"><span class="sidebar-dot amber"></span> Пройдено <span><?= $completed ?></span></a>
    </nav>
    <div class="sidebar-bottom">
      <div class="sidebar-tip"><?= icon('sparkles') ?><strong>Всё под контролем</strong><p>Собирай игры, отмечай прогресс и сохраняй впечатления.</p></div>
      <div class="account"><span class="avatar"><?= h(strtoupper($initial)) ?></span><span><strong><?= h($username) ?></strong><small>Личный профиль</small></span><form class="logout-form" method="post" action="/action.php"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button title="Выйти из аккаунта" type="submit">Выйти</button></form></div>
    </div>
  </aside>
  <main class="main">
    <header class="topbar">
      <div class="mobile-brand"><span class="brand-mark"><?= icon('gamepad',20) ?></span><b>PLAYVAULT</b></div>
      <span class="breadcrumbs">Моё пространство <span>/</span> <b>Обзор</b></span>
      <div class="topbar-right"><span class="live-dot"></span> PHP + PostgreSQL <span class="top-avatar"><?= h(strtoupper($initial)) ?></span><form class="logout-mobile" method="post" action="/action.php"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit" title="Выйти">Выход</button></form></div>
    </header>
    <div class="page">
      <?php if ($flash): ?><div class="notice notice-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
      <div class="page-title">
        <div><p class="eyebrow">ТВОЙ ИГРОВОЙ МИР</p><h1>Привет, <?= h($username) ?> <span>✌</span></h1><p>Держи любимые игры и весь прогресс в одном месте.</p></div>
        <span class="date-badge"><?= icon('gamepad',15) ?> PLAY MORE, WORRY LESS</span>
      </div>
      <section class="hero">
        <div class="hero-copy">
          <span class="hero-eyebrow"><span class="tiny-star">✦</span> YOUR PERSONAL GAME ARCHIVE</span>
          <h2>Каждая игра —<br><em>твоя история.</em></h2>
          <p>От первого запуска до финальных титров. Организуй коллекцию так, как удобно тебе.</p>
          <a href="#new-game" class="hero-button">Добавить игру <?= icon('plus',17) ?></a>
        </div>
        <div class="hero-art" aria-hidden="true"><div class="hero-aura"></div><div class="hero-grid"></div><div class="hero-ring one"></div><div class="hero-ring two"></div><div class="hero-controller"><?= icon('gamepad',112) ?></div><span class="hero-plus a">✦</span><span class="hero-plus b">+</span><div class="hero-chip">LEVEL UP <?= icon('arrow',14) ?></div></div>
      </section>
      <section id="stats" class="stats-grid" aria-label="Статистика коллекции">
        <div class="stat"><div class="stat-icon purple"><?= icon('layers',20) ?></div><span>Всего игр</span><strong><?= str_pad((string) $total, 2, '0', STR_PAD_LEFT) ?></strong><small>В библиотеке</small></div>
        <div class="stat"><div class="stat-icon lime"><?= icon('zap',20) ?></div><span>Сейчас играю</span><strong><?= str_pad((string) $playing, 2, '0', STR_PAD_LEFT) ?></strong><small>Активных прохождений</small></div>
        <div class="stat"><div class="stat-icon orange"><?= icon('trophy',20) ?></div><span>Пройдено</span><strong><?= str_pad((string) $completed, 2, '0', STR_PAD_LEFT) ?></strong><small>Завершённых историй</small></div>
        <div class="stat"><div class="stat-icon pink"><?= icon('clock',20) ?></div><span>Часов в игре</span><strong><?= $totalHours ?></strong><small>Средняя оценка <?= h($average) ?></small></div>
      </section>
      <div class="workspace-grid">
        <section id="collection" class="collection">
          <div class="section-head"><div><p class="eyebrow">ТВОЯ БИБЛИОТЕКА</p><h2>Коллекция <span><?= count($games) ?></span></h2></div><span class="section-desc">Все игры в одном месте</span></div>
          <form method="get" action="/" class="toolbar">
            <label class="searchbox"><?= icon('search') ?><input type="search" name="q" placeholder="Поиск игр..." value="<?= h($q) ?>" aria-label="Поиск игр"></label>
            <label class="toolbar-select"><span class="sr-only">Статус</span><select name="status">
              <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Все статусы</option>
              <option value="playing" <?= $status === 'playing' ? 'selected' : '' ?>>Играю</option>
              <option value="backlog" <?= $status === 'backlog' ? 'selected' : '' ?>>В планах</option>
              <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Пройдено</option>
              <option value="favorites" <?= $status === 'favorites' ? 'selected' : '' ?>>Избранное</option>
            </select><?= icon('chevron',14) ?></label>
            <label class="toolbar-select"><span class="sr-only">Жанр</span><select name="genre"><option value="all">Все жанры</option><?php options(array_column($genres, 'name'), $genre); ?></select><?= icon('chevron',14) ?></label>
            <label class="toolbar-select sort"><?= icon('sort',15) ?><select name="sort"><option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Новые</option><option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>По рейтингу</option><option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>По названию</option></select></label>
            <button class="filter-button" type="submit">Найти <?= icon('arrow',14) ?></button>
          </form>
          <?php if ($games): ?>
            <div class="game-grid">
              <?php foreach ($games as $game): ?>
                <?php $isFavorite = in_array($game['favorite'], [true, 1, '1', 't', 'true'], true); ?>
                <article class="game-card">
                  <div class="game-cover <?= h($themes[$game['genre']] ?? 'graphite') ?>">
                    <div class="cover-glow"></div><div class="cover-orbit"></div>
                    <span class="cover-top"><?= icon('gamepad',15) ?> PLAYVAULT / <?= h(strtoupper($game['genre'])) ?></span>
                    <strong class="cover-name"><?= h($game['title']) ?></strong>
                    <span class="cover-bottom"><?= h($game['platform']) ?> <?= icon('arrow',16) ?></span>
                    <form action="/action.php" method="post" class="cover-heart">
                      <?= csrf_field() ?><input type="hidden" name="action" value="favorite"><input type="hidden" name="id" value="<?= (int) $game['id'] ?>">
                      <button type="submit" title="<?= $isFavorite ? 'Убрать из избранного' : 'В избранное' ?>" aria-label="Изменить избранное" class="<?= $isFavorite ? 'liked' : '' ?>"><?= icon('heart',17) ?></button>
                    </form>
                  </div>
                  <div class="game-content">
                    <div class="game-meta"><span class="status-pill <?= h($game['status']) ?>"><span class="status-dot"></span><?= h($statusLabels[$game['status']] ?? $game['status']) ?></span><span class="platform-tag"><?= h($game['platform']) ?></span></div>
                    <h3 title="<?= h($game['title']) ?>"><?= h($game['title']) ?></h3>
                    <div class="game-details"><span><?= icon('clock',14) ?> <?= (int) $game['hours_played'] ?> ч.</span><span><?= icon('star',14) ?> <?= (int) $game['rating'] ? (int) $game['rating'] . '/10' : 'Без оценки' ?></span><?php if ($game['release_year'] !== null): ?><span><?= (int) $game['release_year'] ?> г.</span><?php endif; ?></div>
                    <?php if ($game['note'] !== ''): ?><p class="game-note"><?= nl2br(h($game['note'])) ?></p><?php endif; ?>
                    <div class="game-bottom">
                      <form method="post" action="/action.php">
                        <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $game['id'] ?>">
                        <select name="status" aria-label="Статус <?= h($game['title']) ?>"><option value="backlog" <?= $game['status'] === 'backlog' ? 'selected' : '' ?>>В планах</option><option value="playing" <?= $game['status'] === 'playing' ? 'selected' : '' ?>>Играю</option><option value="completed" <?= $game['status'] === 'completed' ? 'selected' : '' ?>>Пройдено</option></select>
                        <button type="submit" class="small-apply" title="Применить статус"><?= icon('check',14) ?></button>
                      </form>
                      <details class="edit-details">
                        <summary>Изменить <?= icon('chevron',13) ?></summary>
                        <div class="edit-flyout">
                          <form method="post" action="/action.php" class="edit-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $game['id'] ?>">
                            <?php game_fields($genres, $platforms, $game); ?>
                            <button type="submit" class="btn-primary span-all">Сохранить изменения</button>
                          </form>
                          <form method="post" action="/action.php" class="delete-form" data-confirm-delete>
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $game['id'] ?>">
                            <button type="submit"><?= icon('x',14) ?> Удалить игру</button>
                          </form>
                        </div>
                      </details>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty"><span class="empty-icon"><?= icon('gamepad',30) ?></span>
              <h3><?= $total ? 'Ничего не найдено' : 'Твоя коллекция ждёт игр' ?></h3>
              <p><?= $total ? 'Попробуй изменить поиск или фильтры.' : 'Добавь первую игру вручную или загрузи примеры, чтобы посмотреть все возможности.' ?></p>
              <?php if ($total): ?><a class="btn-secondary" href="/#collection">Сбросить фильтры</a>
              <?php else: ?><form method="post" action="/action.php"><?= csrf_field() ?><input type="hidden" name="action" value="demo"><button class="btn-secondary" type="submit"><?= icon('sparkles',16) ?> Загрузить 5 примеров</button></form><?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="footer-note"><span>© 2026 PLAYVAULT</span><span>Сделано для тех, кто любит игры <?= icon('heart',12) ?></span></div>
        </section>
        <aside class="right-rail">
          <section id="new-game" class="add-panel">
            <div class="form-head"><span class="form-icon"><?= icon('plus',21) ?></span><span><strong>Новая игра</strong><small>Пополнить коллекцию</small></span></div>
            <form method="post" action="/action.php" class="game-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="add">
              <?php game_fields($genres, $platforms); ?>
              <button type="submit" class="btn-primary span-all"><?= icon('plus',17) ?> Добавить в библиотеку</button>
            </form>
          </section>
          <section class="insight-card"><span class="insight-icon"><?= icon('book',21) ?></span><strong>Твой прогресс</strong><p>Уже пройдено <?= $completed ?> из <?= $total ?> игр. Так держать!</p><div class="progress-track"><span style="width:<?= $progress ?>%"></span></div><small><?= $progress ?>% завершено</small></section>
        </aside>
      </div>
    </div>
  </main>
</div>
</body>
</html>
