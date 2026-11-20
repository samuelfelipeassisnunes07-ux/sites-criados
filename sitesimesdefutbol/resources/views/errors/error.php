<?php

declare(strict_types=1);
?>
<div class="container">
    <div class="error-page">
        <div class="error-page__code"><?= (int) ($status ?? 500) ?></div>
        <h1><?= e($title ?? 'Erro') ?></h1>
        <p><?= e($message ?? 'Não foi possível concluir a operação.') ?></p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:20px">
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">Voltar para a loja</a>
            <a class="btn btn--secondary" href="<?= e(url('/catalogo')) ?>">Ver catálogo</a>
        </div>
    </div>
</div>
