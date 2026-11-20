<?php

declare(strict_types=1);

$flashes = pull_flashes();
$pageTitle = isset($title) && $title !== '' ? $title . ' | ' . setting('store_name', 'Simês de Fútbol') : setting('store_name', 'Simês de Fútbol');
$metaDescription = $metaDescription ?? setting('store_slogan', 'Loja de futebol');
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#064e3b">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="store-body <?= e($bodyClass) ?>">
    <a class="sr-only" href="#conteudo">Ir para o conteúdo</a>

    <?php require BASE_PATH . '/resources/views/partials/header.php'; ?>

    <main id="conteudo" class="site-main">
        <?php if ($flashes !== []): ?>
            <?php require BASE_PATH . '/resources/views/partials/flash.php'; ?>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <?php require BASE_PATH . '/resources/views/partials/footer.php'; ?>

    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
