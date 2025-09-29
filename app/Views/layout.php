<?php
/** @var string $content */
/** @var string $lang */
/** @var array $translations */
/** @var string $nonce */
$css = file_get_contents(__DIR__ . '/../Assets/app.css');
$js = file_get_contents(__DIR__ . '/../Assets/app.js');
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang ?? 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($translations['title'] ?? 'Checkers', ENT_QUOTES, 'UTF-8') ?></title>
    <style><?= $css ?></style>
</head>
<body>
<?= $content ?>
<script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>"><?= $js ?></script>
</body>
</html>
