<?php

declare(strict_types=1);

/**
 * Card de produto.
 *
 * Espera: $product (array), opcional $favoriteIds (array<int>).
 */
$isFavorite = isset($favoriteIds) && in_array((int) ($product['id'] ?? 0), $favoriteIds, true);
$stock = (int) ($product['stock_quantity'] ?? 0);
$promo = product_old_price($product) !== null;
$productUrl = url('/produto/' . rawurlencode((string) $product['slug']));
?>
<article class="product-card">
    <a class="product-card__media" href="<?= e($productUrl) ?>" aria-label="<?= e($product['name']) ?>">
        <img src="<?= e(img_url($product['image'] ?? null)) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="400" height="400">
        <?php if ($promo): ?>
            <span class="product-card__flag badge badge--danger">-<?= product_discount_percent($product) ?>%</span>
        <?php endif; ?>
        <?php if ($stock <= 0): ?>
            <span class="product-card__soldout">Esgotado</span>
        <?php endif; ?>
    </a>

    <?php if (is_logged()): ?>
        <form method="post" action="<?= e(url('/favoritos/alternar')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <button type="submit" class="product-card__favorite<?= $isFavorite ? ' is-active' : '' ?>"
                    title="<?= $isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>"
                    aria-label="<?= $isFavorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">
                <?= $isFavorite ? '♥' : '♡' ?>
            </button>
        </form>
    <?php endif; ?>

    <div class="product-card__body">
        <span class="product-card__category"><?= e($product['category_name'] ?? $product['category_slug'] ?? '') ?></span>
        <h3 class="product-card__title"><a href="<?= e($productUrl) ?>"><?= e($product['name']) ?></a></h3>

        <div class="product-card__price-row">
            <span class="price<?= $promo ? ' price--promo' : '' ?>"><?= e(money(product_price($product))) ?></span>
            <?php if ($promo): ?>
                <span class="price--old"><?= e(money((float) $product['price'])) ?></span>
            <?php endif; ?>
        </div>
        <span class="product-card__installment">
            ou 3x de <?= e(money(product_price($product) / 3)) ?> sem juros
        </span>

        <div class="product-card__actions">
            <?php if ($stock > 0): ?>
                <form method="post" action="<?= e(url('/carrinho/adicionar')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="btn btn--primary btn--sm">Comprar</button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn--secondary btn--sm" disabled>Indisponível</button>
            <?php endif; ?>
        </div>
    </div>
</article>
