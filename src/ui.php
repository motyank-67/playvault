<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function icon(string $name, int $size = 18): string
{
    $paths = [
        'gamepad' => '<path d="M6 12h4m-2-2v4m6-1h.01M17 11h.01"/><path d="M7.2 6.5h9.6a4 4 0 0 1 3.9 3l1.2 5a3 3 0 0 1-4.8 3.1L15 16H9l-2.1 1.6a3 3 0 0 1-4.8-3.1l1.2-5a4 4 0 0 1 3.9-3Z"/>',
        'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'trophy' => '<path d="M8 21h8m-4-4v4M6 3h12v7a6 6 0 0 1-12 0V3ZM6 6H3v2a4 4 0 0 0 4 4m11-6h3v2a4 4 0 0 1-4 4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'star' => '<path d="m12 2 3.1 6.4 7 .9-5.1 5 .9 7-5.9-3.3L6.1 21l.9-7-5.1-5 7-.9L12 2Z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="7.2"/><path d="m16 16 5 5"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'arrow' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'sparkles' => '<path d="m12 2 1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8L12 2ZM5 17l.8 2.2L8 20l-2.2.8L5 23l-.8-2.2L2 20l2.2-.8L5 17Z"/>',
        'book' => '<path d="M12 7v14M3 5a8 8 0 0 1 9 2 8 8 0 0 1 9-2v14a8 8 0 0 0-9 2 8 8 0 0 0-9-2V5Z"/>',
        'zap' => '<path d="m13 2-9 12h7l-1 8 10-12h-7l0-8Z"/>',
        'sort' => '<path d="m7 3-4 4h8L7 3Zm0 18 4-4H3l4 4ZM17 3v18m-3-3 3 3 3-3"/>',
        'x' => '<path d="M5 5l14 14M19 5 5 19"/>',
    ];
    $path = $paths[$name] ?? $paths['gamepad'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function options(array $values, string $selected): void
{
    foreach ($values as $value) {
        echo '<option value="' . h($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . h($value) . '</option>';
    }
}

function reference_options(array $rows, int $selected): void
{
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        echo '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>' . h($row['name']) . '</option>';
    }
}

function game_fields(array $genres, array $platforms, array $game = []): void
{
    $title = $game['title'] ?? '';
    $platformId = (int) ($game['platform_id'] ?? ($platforms[0]['id'] ?? 0));
    $genreId = (int) ($game['genre_id'] ?? ($genres[0]['id'] ?? 0));
    $releaseYear = $game['release_year'] ?? '';
    $status = $game['status'] ?? 'backlog';
    $rating = $game['rating'] ?? 0;
    $hours = $game['hours_played'] ?? 0;
    $note = $game['note'] ?? '';
    ?>
    <label class="field span-all">
      <span>Название игры <b>*</b></span>
      <input name="title" placeholder="Например, Stardew Valley" value="<?= h($title) ?>" maxlength="120" required>
    </label>
    <label class="field">
      <span>Платформа</span>
      <select name="platform_id" required><?php reference_options($platforms, $platformId); ?></select>
    </label>
    <label class="field">
      <span>Жанр</span>
      <select name="genre_id" required><?php reference_options($genres, $genreId); ?></select>
    </label>
    <label class="field"><span>Год выпуска</span><input type="number" name="release_year" min="1970" max="2100" value="<?= h($releaseYear) ?>" placeholder="Например, 2024"></label>
    <label class="field">
      <span>Статус</span>
      <select name="status">
        <option value="backlog" <?= $status === 'backlog' ? 'selected' : '' ?>>В планах</option>
        <option value="playing" <?= $status === 'playing' ? 'selected' : '' ?>>Играю</option>
        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Пройдено</option>
      </select>
    </label>
    <label class="field"><span>Оценка / 10</span><input type="number" name="rating" min="0" max="10" value="<?= h($rating) ?>"></label>
    <label class="field"><span>Часов в игре</span><input type="number" name="hours_played" min="0" max="9999" value="<?= h($hours) ?>"></label>
    <label class="field span-all"><span>Заметка</span><textarea name="note" rows="3" maxlength="500" placeholder="Что хочется запомнить?"><?= h($note) ?></textarea></label>
    <?php
}
