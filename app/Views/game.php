<?php
/** @var array $game */
/** @var array $translations */
/** @var string $lang */
/** @var string $csrf */
/** @var string $shareUrl */
$status = $game['winner'] ? sprintf($translations['game_over'], $game['names'][$game['winner']] ?? $translations[$game['winner'] === 'r' ? 'red' : 'black']) : ($game['draw'] ? $translations['draw'] : sprintf($translations['to_move'], $game['names'][$game['turn']] ?? $translations[$game['turn'] === 'r' ? 'red' : 'black']));
?>
<header class="top-bar">
    <div class="brand"><?= htmlspecialchars($translations['title'], ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="lang-links">
        <a href="/?action=view&amp;id=<?= urlencode($game['id']) ?>&amp;lang=en">EN</a>
        <a href="/?action=view&amp;id=<?= urlencode($game['id']) ?>&amp;lang=es">ES</a>
        <a class="button" href="/?"><?= htmlspecialchars($translations['new_game'], ENT_QUOTES, 'UTF-8') ?></a>
    </nav>
</header>
<main class="container game" id="game-root"
    data-game-id="<?= htmlspecialchars($game['id'], ENT_QUOTES, 'UTF-8') ?>"
    data-lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"
    data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
    data-cap-r="<?= htmlspecialchars($game['capabilities']['r'], ENT_QUOTES, 'UTF-8') ?>"
    data-cap-b="<?= htmlspecialchars($game['capabilities']['b'], ENT_QUOTES, 'UTF-8') ?>"
    data-share="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-status-turn="<?= htmlspecialchars($translations['to_move'], ENT_QUOTES, 'UTF-8') ?>"
    data-status-win="<?= htmlspecialchars($translations['game_over'], ENT_QUOTES, 'UTF-8') ?>"
    data-status-draw="<?= htmlspecialchars($translations['draw'], ENT_QUOTES, 'UTF-8') ?>"
    data-updated="<?= (int)($game['updated_at'] ?? time()) ?>">
    <section class="board" id="board">
        <?php for ($r = 0; $r < 8; $r++): ?>
            <?php for ($c = 0; $c < 8; $c++): ?>
                <?php $piece = $game['board'][$r][$c]; ?>
                <div class="sq <?= (($r + $c) & 1) === 0 ? 'light' : 'dark' ?>" data-r="<?= $r ?>" data-c="<?= $c ?>">
                    <?php if ($piece !== '.'): ?>
                        <?php $side = strtolower($piece) === 'r' ? 'r' : 'b'; ?>
                        <?php $isKing = $piece === 'R' || $piece === 'B'; ?>
                        <div class="piece <?= $side ?><?= $isKing ? ' king' : '' ?>" data-side="<?= $side ?>" title="<?= htmlspecialchars(($side === 'r' ? $translations['red'] : $translations['black']) . ($isKing ? ' (K)' : ''), ENT_QUOTES, 'UTF-8') ?>"><?= $isKing ? '★' : '' ?></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        <?php endfor; ?>
    </section>
    <section class="panel">
        <h1><?= htmlspecialchars($translations['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="status" id="status-text"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="message muted" id="message-text"></div>
        <p class="muted small"><?= htmlspecialchars($translations['turn_note'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="share">
            <label><?= htmlspecialchars($translations['your_link'], ENT_QUOTES, 'UTF-8') ?></label>
            <div class="share-row">
                <input type="text" readonly id="share-link" value="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" class="secondary" data-action="copy"><?= htmlspecialchars($translations['copy'], ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div class="copy-feedback" id="copy-feedback"><?= htmlspecialchars($translations['copied'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="help muted small"><?= htmlspecialchars($translations['help'], ENT_QUOTES, 'UTF-8') ?></div>
    </section>
</main>
<footer class="footer">
    <span>ID: <?= htmlspecialchars($game['id'], ENT_QUOTES, 'UTF-8') ?></span>
</footer>
<script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">window.gameInitial = <?= json_encode([
    'board' => $game['board'],
    'turn' => $game['turn'],
    'winner' => $game['winner'],
    'draw' => $game['draw'],
    'names' => $game['names'],
    'last_move' => $game['last_move'],
], JSON_UNESCAPED_UNICODE) ?>;</script>
