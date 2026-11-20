<?php

declare(strict_types=1);

/** @var array<int,array<string,mixed>> $banners */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int,array<string,mixed>> $featured */
/** @var array<int,array<string,mixed>> $newArrivals */
/** @var array<int,array<string,mixed>> $promotions */

$favoriteIds = is_logged() ? \App\Models\Favorite::productIds((int) auth_user()['id']) : [];
?>
<?php if ($banners !== []): ?>
    <section class="hero">
        <div class="container">
            <div class="hero__viewport" data-hero>
                <div class="hero__track" data-hero-track>
                    <?php foreach ($banners as $index => $banner): ?>
                        <?php
                        $bannerLink = (string) ($banner['link'] ?? '');
                        $bannerHref = $bannerLink !== ''
                            ? (preg_match('#^https?://#i', $bannerLink) ? $bannerLink : url(ltrim($bannerLink, '/')))
                            : url('/catalogo');
                        ?>
                        <article class="hero__slide">
                            <img class="hero__bg" src="<?= e(img_url($banner['image'] ?? null, 'assets/img/placeholder.svg')) ?>" alt="<?= e($banner['title'] ?? 'Banner') ?>">
                            <div class="hero__content">
                                <span class="hero__eyebrow">⚽ <?= e(setting('store_name', 'Simês de Fútbol')) ?> · Coleção 2026</span>
                                <h1 class="hero__title"><?= e($banner['title'] ?? '') ?></h1>
                                <?php if (!empty($banner['subtitle'])): ?>
                                    <p class="hero__subtitle"><?= e($banner['subtitle']) ?></p>
                                <?php endif; ?>
                                <div class="hero__actions">
                                    <a class="btn btn--primary btn--lg" href="<?= e($bannerHref) ?>">Comprar agora</a>
                                    <a class="btn btn--secondary btn--lg" href="<?= e(url('/catalogo')) ?>">Ver catálogo</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (count($banners) > 1): ?>
                    <div class="hero__dots" role="tablist" aria-label="Banners">
                        <?php foreach ($banners as $index => $banner): ?>
                            <button type="button" class="hero__dot<?= $index === 0 ? ' is-active' : '' ?>"
                                    data-slide="<?= $index ?>" aria-label="Banner <?= $index + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero">
        <div class="container">
            <div class="hero__viewport">
                <article class="hero__slide" style="background:linear-gradient(120deg,#04211a,#0b6e4f)">
                    <div class="hero__content">
                        <span class="hero__eyebrow">⚽ <?= e(setting('store_name', 'Simês de Fútbol')) ?></span>
                        <h1 class="hero__title">Paixão que se veste. Futebol que se vive.</h1>
                        <p class="hero__subtitle">Camisas, chuteiras e acessórios para quem joga dentro e fora de campo.</p>
                        <div class="hero__actions">
                            <a class="btn btn--primary btn--lg" href="<?= e(url('/catalogo')) ?>">Ver catálogo</a>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section__head">
            <div>
                <span class="section__eyebrow">Explore</span>
                <h2 class="section__title">Categorias</h2>
            </div>
            <a class="section__link" href="<?= e(url('/catalogo')) ?>">Ver tudo →</a>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a class="category-card" href="<?= e(url('/catalogo?category=' . urlencode((string) $category['slug']))) ?>">
                    <span class="category-card__name"><?= e($category['name']) ?></span>
                    <span class="category-card__count"><?= (int) ($category['product_count'] ?? 0) ?> produto(s)</span>
                    <span class="category-card__arrow">Explorar →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($featured !== []): ?>
    <section class="section">
        <div class="container">
            <div class="section__head">
                <div>
                    <span class="section__eyebrow">Selecionados</span>
                    <h2 class="section__title">Destaques da loja</h2>
                </div>
                <a class="section__link" href="<?= e(url('/catalogo')) ?>">Ver todos →</a>
            </div>

            <div class="product-grid">
                <?php foreach ($featured as $product): ?>
                    <?php require BASE_PATH . '/resources/views/partials/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($promotions !== []): ?>
    <section class="section">
        <div class="container">
            <div class="section__head">
                <div>
                    <span class="section__eyebrow">Ofertas</span>
                    <h2 class="section__title">Promoções da semana</h2>
                </div>
                <a class="section__link" href="<?= e(url('/catalogo?sort=price_asc')) ?>">Ver ofertas →</a>
            </div>

            <div class="product-grid">
                <?php foreach ($promotions as $product): ?>
                    <?php require BASE_PATH . '/resources/views/partials/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($newArrivals !== []): ?>
    <section class="section">
        <div class="container">
            <div class="section__head">
                <div>
                    <span class="section__eyebrow">Novidades</span>
                    <h2 class="section__title">Chegou primeiro aqui</h2>
                </div>
                <a class="section__link" href="<?= e(url('/catalogo?sort=newest')) ?>">Ver novidades →</a>
            </div>

            <div class="product-grid">
                <?php foreach ($newArrivals as $product): ?>
                    <?php require BASE_PATH . '/resources/views/partials/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="trust-strip">
            <div class="trust-item">
                <span class="trust-item__icon">🚚</span>
                <span>
                    <strong>Frete grátis</strong>
                    <span>Em compras acima de <?= e(money((float) config('app.shipping.free_threshold', 299))) ?>.</span>
                </span>
            </div>
            <div class="trust-item">
                <span class="trust-item__icon">🔄</span>
                <span>
                    <strong>Troca fácil</strong>
                    <span>Até 7 dias para trocar ou devolver.</span>
                </span>
            </div>
            <div class="trust-item">
                <span class="trust-item__icon">🔒</span>
                <span>
                    <strong>Compra segura</strong>
                    <span>Seus dados protegidos em todo o site.</span>
                </span>
            </div>
            <div class="trust-item">
                <span class="trust-item__icon">💬</span>
                <span>
                    <strong>Atendimento</strong>
                    <span>Time pronto para te ajudar.</span>
                </span>
            </div>
        </div>
    </div>
</section>
