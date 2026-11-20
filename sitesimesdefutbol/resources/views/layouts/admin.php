<?php

declare(strict_types=1);

$flashes = pull_flashes();
$pageTitle = isset($title) && $title !== '' ? $title . ' | Admin ' . setting('store_name', 'Simês de Fútbol') : 'Painel administrativo';
$admin = auth_user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#04211a">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <?php require BASE_PATH . '/resources/views/partials/admin-sidebar.php'; ?>

        <div class="admin-main">
            <header class="admin-topbar">
                <div style="display:flex;align-items:center;gap:12px">
                    <button type="button" class="admin-menu-toggle" data-toggle-admin-nav aria-label="Abrir menu">☰</button>
                    <div class="admin-topbar__title">
                        <strong><?= e($title ?? 'Painel') ?></strong>
                        <span>Área administrativa</span>
                    </div>
                </div>
                <div class="admin-topbar__actions">
                    <a class="btn btn--secondary btn--sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver loja</a>
                    <a class="header-action" href="<?= e(url('/admin')) ?>" style="padding:4px 6px">
                        <span class="avatar" style="width:36px;height:36px;font-size:.75rem"><?= e(initials((string) ($admin['name'] ?? 'Admin'))) ?></span>
                    </a>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Sair</button>
                    </form>
                </div>
            </header>

            <div class="admin-content">
                <?php if ($flashes !== []): ?>
                    <?php require BASE_PATH . '/resources/views/partials/flash.php'; ?>
                <?php endif; ?>

                <?= $content ?>
            </div>
        </div>
    </div>

    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
