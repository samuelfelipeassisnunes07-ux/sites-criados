<?php

declare(strict_types=1);

$admin = auth_user();
$current = \App\Core\Request::path();

$items = [
    ['label' => 'Dashboard', 'path' => '/admin', 'icon' => '📊'],
    ['label' => 'Produtos', 'path' => '/admin/produtos', 'icon' => '📦'],
    ['label' => 'Categorias', 'path' => '/admin/categorias', 'icon' => '🏷️'],
    ['label' => 'Pedidos', 'path' => '/admin/pedidos', 'icon' => '🧾'],
    ['label' => 'Clientes', 'path' => '/admin/clientes', 'icon' => '👥'],
    ['label' => 'Cupons', 'path' => '/admin/cupons', 'icon' => '🎟️'],
    ['label' => 'Banners', 'path' => '/admin/banners', 'icon' => '🖼️'],
    ['label' => 'Relatórios', 'path' => '/admin/relatorios', 'icon' => '📈'],
    ['label' => 'Configurações', 'path' => '/admin/configuracoes', 'icon' => '⚙️'],
    ['label' => 'Logs', 'path' => '/admin/logs', 'icon' => '🗂️'],
];

$isActive = static function (string $path) use ($current): bool {
    if ($path === '/admin') {
        return $current === '/admin';
    }

    return str_starts_with($current, $path);
};
?>
<aside class="admin-sidebar" data-admin-sidebar>
    <a class="admin-sidebar__brand" href="<?= e(url('/admin')) ?>">
        <span class="brand__mark">⚽</span>
        <span class="brand__text">
            <span class="brand__name" style="color:#fff"><?= e(setting('store_name', 'Simês de Fútbol')) ?></span>
            <span class="admin-sidebar__badge">Painel administrativo</span>
        </span>
    </a>

    <nav class="admin-nav" aria-label="Menu administrativo">
        <span class="admin-nav__label">Gestão</span>
        <?php foreach ($items as $item): ?>
            <a class="<?= $isActive($item['path']) ? 'is-active' : '' ?>" href="<?= e(url($item['path'])) ?>">
                <span class="admin-nav__icon"><?= $item['icon'] ?></span>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="admin-sidebar__footer">
        <div style="display:flex;align-items:center;gap:10px;padding:0 8px 10px">
            <span class="avatar" style="width:36px;height:36px;font-size:.72rem"><?= e(initials((string) ($admin['name'] ?? 'Admin'))) ?></span>
            <span style="line-height:1.2">
                <span style="display:block;color:#fff;font-size:.82rem;font-weight:700"><?= e($admin['name'] ?? 'Administrador') ?></span>
                <span style="font-size:.7rem;color:#8fbda9"><?= e($admin['email'] ?? '') ?></span>
            </span>
        </div>
        <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">↗ Ver loja</a>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--sm" style="color:#c9e3d7;width:100%;justify-content:flex-start">Sair da conta</button>
        </form>
    </div>
</aside>
