<?php

declare(strict_types=1);

try {
    $navCategories = \App\Models\Category::active();
} catch (\Throwable) {
    $navCategories = [];
}

try {
    $cartQuantity = \App\Services\CartService::countForHeader(\App\Core\Auth::id());
} catch (\Throwable) {
    $cartQuantity = 0;
}

$storeName = setting('store_name', 'Simês de Fútbol');
$logo = setting('logo_image', '');
$user = auth_user();
$currentPath = \App\Core\Request::path();
?>
<div class="topbar">
    <div class="container topbar__inner">
        <ul class="topbar__list">
            <li>⚽ Frete grátis acima de <?= e(money((float) config('app.shipping.free_threshold', 299))) ?></li>
            <li>🚚 Enviamos para todo o Brasil</li>
        </ul>
        <ul class="topbar__list">
            <?php if (setting('contact_phone', '') !== ''): ?>
                <li>📞 <?= e(setting('contact_phone')) ?></li>
            <?php endif; ?>
            <li><a href="<?= e(url('/contato')) ?>">Atendimento</a></li>
        </ul>
    </div>
</div>

<header class="site-header">
    <div class="container header-main">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($storeName) ?> - página inicial">
            <span class="brand__mark">
                <?php if ($logo !== ''): ?>
                    <img src="<?= e(img_url($logo)) ?>" alt="<?= e($storeName) ?>">
                <?php else: ?>
                    ⚽
                <?php endif; ?>
            </span>
            <span class="brand__text">
                <span class="brand__name"><?= e($storeName) ?></span>
                <span class="brand__tagline"><?= e(setting('store_slogan', 'Paixão que se veste')) ?></span>
            </span>
        </a>

        <form class="header-search" action="<?= e(url('/catalogo')) ?>" method="get" role="search">
            <label class="sr-only" for="busca">Buscar produtos</label>
            <input id="busca" type="search" name="q" placeholder="Busque por camisa, chuteira, bola..." value="<?= e((string) \App\Core\Request::input('q', '')) ?>" maxlength="120">
            <button type="submit" aria-label="Buscar">🔍</button>
        </form>

        <div class="header-actions">
            <a class="header-action" href="<?= e($user !== null ? url($user['role'] === 'admin' ? '/admin' : '/minha-conta') : url('/login')) ?>">
                <span class="header-action__icon"><?= $user !== null ? '👤' : '👤' ?></span>
                <span class="header-action__label">
                    <?= $user !== null ? 'Olá, ' . e(explode(' ', (string) $user['name'])[0]) : 'Entrar' ?>
                    <small><?= $user !== null ? 'Minha conta' : 'Cadastre-se' ?></small>
                </span>
            </a>
            <a class="header-action" href="<?= e(url('/favoritos')) ?>" aria-label="Favoritos">
                <span class="header-action__icon">❤️</span>
                <span class="header-action__label">Favoritos<small>Meus itens</small></span>
            </a>
            <a class="header-action" href="<?= e(url('/carrinho')) ?>" aria-label="Carrinho">
                <span class="header-action__icon">🛒</span>
                <span class="header-action__label">Carrinho<small>Compras</small></span>
                <?php if ($cartQuantity > 0): ?>
                    <span class="cart-count"><?= $cartQuantity ?></span>
                <?php endif; ?>
            </a>
            <button class="mobile-toggle" type="button" data-open-nav aria-label="Abrir menu">☰</button>
        </div>
    </div>

    <nav class="main-nav" aria-label="Categorias e navegação principal">
        <div class="container main-nav__inner">
            <a class="nav-link nav-link--highlight <?= active_path('/catalogo') ?>" href="<?= e(url('/catalogo')) ?>">Catálogo completo</a>
            <?php foreach ($navCategories as $navCategory): ?>
                <a class="nav-link <?= (string) \App\Core\Request::input('category', '') === (string) $navCategory['slug'] ? 'is-active' : '' ?>"
                   href="<?= e(url('/catalogo?category=' . urlencode((string) $navCategory['slug']))) ?>">
                    <?= e($navCategory['name']) ?>
                </a>
            <?php endforeach; ?>
            <span class="nav-spacer"></span>
            <a class="nav-link <?= $currentPath === '/sobre' ? 'is-active' : '' ?>" href="<?= e(url('/sobre')) ?>">Sobre</a>
            <a class="nav-link <?= $currentPath === '/faq' ? 'is-active' : '' ?>" href="<?= e(url('/faq')) ?>">Dúvidas</a>
            <a class="nav-link <?= $currentPath === '/contato' ? 'is-active' : '' ?>" href="<?= e(url('/contato')) ?>">Contato</a>
        </div>
    </nav>
</header>

<div class="mobile-nav" data-mobile-nav>
    <div class="mobile-nav__backdrop" data-close-nav></div>
    <aside class="mobile-nav__panel" aria-label="Menu">
        <div class="mobile-nav__head">
            <strong><?= e($storeName) ?></strong>
            <button type="button" class="btn btn--ghost btn--sm" data-close-nav aria-label="Fechar menu">✕</button>
        </div>
        <form class="header-search" action="<?= e(url('/catalogo')) ?>" method="get" role="search">
            <input type="search" name="q" placeholder="Buscar produtos..." value="">
            <button type="submit" aria-label="Buscar">🔍</button>
        </form>
        <div class="mobile-nav__links">
            <a href="<?= e(url('/catalogo')) ?>">Catálogo completo</a>
            <?php foreach ($navCategories as $navCategory): ?>
                <a href="<?= e(url('/catalogo?category=' . urlencode((string) $navCategory['slug']))) ?>"><?= e($navCategory['name']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(url('/favoritos')) ?>">Meus favoritos</a>
            <a href="<?= e(url('/carrinho')) ?>">Carrinho (<?= $cartQuantity ?>)</a>
            <a href="<?= e($user !== null ? url('/minha-conta') : url('/login')) ?>"><?= $user !== null ? 'Minha conta' : 'Entrar / Cadastrar' ?></a>
            <?php if ($user !== null): ?>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--secondary btn--block">Sair</button>
                </form>
            <?php endif; ?>
        </div>
    </aside>
</div>
