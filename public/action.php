<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

try {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Ты вышел из аккаунта.');
        header('Location: /auth.php', true, 303);
        exit;
    }
    $userId = require_user_id();
    $pdo = db();

    switch ($action) {
        case 'add':
            $game = game_from_post();
            $pdo->beginTransaction();
            $gameId = catalog_game_id($pdo, $game['title'], $game['genre_id'], $game['release_year']);
            $statement = $pdo->prepare(
                'INSERT INTO collection_items (user_id, game_id, platform_id, status, rating, hours_played, note)
                 VALUES (:user_id, :game_id, :platform_id, :status, :rating, :hours_played, :note)'
            );
            $statement->execute([
                'user_id' => $userId,
                'game_id' => $gameId,
                'platform_id' => $game['platform_id'],
                'status' => $game['status'],
                'rating' => $game['rating'],
                'hours_played' => $game['hours_played'],
                'note' => $game['note'],
            ]);
            $pdo->commit();
            flash('Игра добавлена в коллекцию!');
            break;

        case 'update':
            $game = game_from_post();
            $itemId = positive_id();
            $pdo->beginTransaction();
            $owner = $pdo->prepare('SELECT id FROM collection_items WHERE id = :id AND user_id = :user_id FOR UPDATE');
            $owner->execute(['id' => $itemId, 'user_id' => $userId]);
            if ($owner->fetchColumn() === false) {
                throw new InvalidArgumentException('Игра не найдена в твоей коллекции.');
            }
            $gameId = catalog_game_id($pdo, $game['title'], $game['genre_id'], $game['release_year']);
            $statement = $pdo->prepare(
                'UPDATE collection_items
                 SET game_id = :game_id, platform_id = :platform_id,
                     status = :status, rating = :rating,
                     hours_played = :hours_played, note = :note
                 WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'id' => $itemId,
                'user_id' => $userId,
                'game_id' => $gameId,
                'platform_id' => $game['platform_id'],
                'status' => $game['status'],
                'rating' => $game['rating'],
                'hours_played' => $game['hours_played'],
                'note' => $game['note'],
            ]);
            $pdo->commit();
            flash('Изменения сохранены.');
            break;

        case 'delete':
            $statement = $pdo->prepare('DELETE FROM collection_items WHERE id = :id AND user_id = :user_id');
            $statement->execute(['id' => positive_id(), 'user_id' => $userId]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Игра не найдена в твоей коллекции.');
            }
            flash('Игра удалена.');
            break;

        case 'favorite':
            $statement = $pdo->prepare('UPDATE collection_items SET favorite = NOT favorite WHERE id = :id AND user_id = :user_id');
            $statement->execute(['id' => positive_id(), 'user_id' => $userId]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Игра не найдена в твоей коллекции.');
            }
            flash('Избранное обновлено.');
            break;

        case 'status':
            $status = allowed_value($_POST['status'] ?? '', STATUSES, 'backlog');
            $statement = $pdo->prepare('UPDATE collection_items SET status = :status WHERE id = :id AND user_id = :user_id');
            $statement->execute(['status' => $status, 'id' => positive_id(), 'user_id' => $userId]);
            if ($statement->rowCount() === 0) {
                throw new InvalidArgumentException('Игра не найдена в твоей коллекции.');
            }
            flash('Статус обновлён.');
            break;

        case 'demo':
            $pdo->beginTransaction();
            $counter = $pdo->prepare('SELECT COUNT(*) FROM collection_items WHERE user_id = :user_id');
            $counter->execute(['user_id' => $userId]);
            $count = (int) $counter->fetchColumn();
            if ($count === 0) {
                $genreIds = array_column($pdo->query('SELECT id, name FROM genres')->fetchAll(), 'id', 'name');
                $platformIds = array_column($pdo->query('SELECT id, name FROM platforms')->fetchAll(), 'id', 'name');
                $statement = $pdo->prepare(
                    'INSERT INTO collection_items
                     (user_id, game_id, platform_id, status, rating, hours_played, note, favorite)
                     VALUES (:user_id, :game_id, :platform_id, :status, :rating, :hours, :note, :favorite)'
                );
                $examples = [
                    ['Cyberpunk 2077', 'PC', 'RPG', 'playing', 9, 68, 'Прохожу сюжет за нетраннера', true],
                    ['Hollow Knight', 'PC', 'Indie', 'completed', 10, 42, 'Лучший саундтрек', true],
                    ['Elden Ring', 'PlayStation', 'RPG', 'backlog', 0, 0, 'Начать на каникулах', false],
                    ['Minecraft', 'PC', 'Adventure', 'playing', 9, 320, 'Выживание с друзьями', false],
                    ['Portal 2', 'PC', 'Adventure', 'completed', 10, 18, 'Перепройти кооператив', true],
                ];
                foreach ($examples as [$title, $platform, $genre, $status, $rating, $hours, $note, $favorite]) {
                    if (!isset($genreIds[$genre], $platformIds[$platform])) {
                        throw new RuntimeException('В справочниках нет необходимых жанров или платформ.');
                    }
                    $gameId = catalog_game_id($pdo, $title, (int) $genreIds[$genre], null);
                    $statement->execute([
                        'user_id' => $userId,
                        'game_id' => $gameId,
                        'platform_id' => $platformIds[$platform],
                        'status' => $status,
                        'rating' => $rating,
                        'hours' => $hours,
                        'note' => $note,
                        'favorite' => $favorite ? 'true' : 'false',
                    ]);
                }
                flash('Добавлено 5 демонстрационных игр.');
            } else {
                flash('Примеры можно загрузить только в пустую коллекцию.', 'info');
            }
            $pdo->commit();
            break;

        default:
            throw new InvalidArgumentException('Неизвестное действие.');
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error instanceof PDOException) {
        $message = $error->getCode() === '23505'
            ? 'Эта игра на выбранной платформе уже есть в коллекции.'
            : 'Ошибка базы данных. Проверь подключение и структуру базы.';
    } else {
        $message = $error->getMessage();
    }
    flash($message, 'error');
}

redirect_home();
