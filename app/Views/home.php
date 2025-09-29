<?php
/** @var array $translations */
/** @var array $recent */
/** @var array $scoreboard */
/** @var string $csrf */
/** @var string $lang */
/** @var array|null $flash */
?>
<header class="top-bar">
    <div class="brand"><?= htmlspecialchars($translations['title'], ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="lang-links">
        <a href="/?lang=en">EN</a>
        <a href="/?lang=es">ES</a>
    </nav>
</header>
<main class="container home">
    <section class="card new-game">
        <h1><?= htmlspecialchars($translations['new_game'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="muted"><?= htmlspecialchars($translations['help'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="new">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="lang" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                <span><?= htmlspecialchars($translations['name_red'], ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" name="rname" maxlength="40" placeholder="<?= htmlspecialchars($translations['red'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span><?= htmlspecialchars($translations['name_black'], ENT_QUOTES, 'UTF-8') ?></span>
                <input type="text" name="bname" maxlength="40" placeholder="<?= htmlspecialchars($translations['black'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <label>
                <span>Email (Red)</span>
                <input type="email" name="email_red" maxlength="120" placeholder="red@example.com">
            </label>
            <label>
                <span>Email (Black)</span>
                <input type="email" name="email_black" maxlength="120" placeholder="black@example.com">
            </label>
            <label>
                <span><?= htmlspecialchars($translations['mode'], ENT_QUOTES, 'UTF-8') ?></span>
                <select name="mode">
                    <option value="shared"><?= htmlspecialchars($translations['shared'], ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="hotseat"><?= htmlspecialchars($translations['hotseat'], ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </label>
            <button type="submit" class="primary"><?= htmlspecialchars($translations['new_game'], ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    </section>
    <section class="card scoreboard">
        <h2><?= htmlspecialchars($translations['scoreboard'], ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (empty($scoreboard)): ?>
            <p class="muted small"><?= htmlspecialchars($translations['no_games'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($translations['player'], ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($translations['wins'], ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($translations['losses'], ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($translations['draws'], ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($translations['moves'], ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scoreboard as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$row['wins'] ?></td>
                            <td><?= (int)$row['losses'] ?></td>
                            <td><?= (int)$row['draws'] ?></td>
                            <td><?= (int)$row['moves'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <section class="card recent">
        <h2><?= htmlspecialchars($translations['recent_games'], ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (empty($recent)): ?>
            <p class="muted small"><?= htmlspecialchars($translations['no_games'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <ul class="recent-list">
                <?php foreach ($recent as $game): ?>
                    <li>
                        <a href="/?action=view&amp;id=<?= urlencode($game['id']) ?>&amp;lang=<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(($game['name_red'] ?: 'Red') . ' vs ' . ($game['name_black'] ?: 'Black'), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <span class="muted small">
                            <?= htmlspecialchars(date('Y-m-d H:i', (int)$game['updated_at']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<footer class="footer">
    <span>© <?= date('Y') ?> Simple Checkers</span>
</footer>
