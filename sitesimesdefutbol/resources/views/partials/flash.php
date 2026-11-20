<?php

declare(strict_types=1);

/** @var array<string,mixed> $flashes */
?>
<div class="container flash-stack">
    <?php foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $type => $tone): ?>
        <?php if (!empty($flashes[$type])): ?>
            <div class="alert alert--<?= e($tone) ?>" role="alert">
                <span class="alert__icon"><?= $tone === 'success' ? '✓' : ($tone === 'error' ? '!' : 'i') ?></span>
                <span><?= e((string) $flashes[$type]) ?></span>
                <button type="button" class="alert__close" data-dismiss aria-label="Fechar">×</button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
