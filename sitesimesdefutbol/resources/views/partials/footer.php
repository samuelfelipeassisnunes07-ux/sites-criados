<?php

declare(strict_types=1);

try {
    $footerCategories = \App\Models\Category::active();
} catch (\Throwable) {
    $footerCategories = [];
}
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="<?= e(url('/')) ?>">
                    <span class="brand__mark">⚽</span>
                    <span class="brand__text">
                        <span class="brand__name"><?= e(setting('store_name', 'Simês de Fútbol')) ?></span>
                        <span class="brand__tagline"><?= e(setting('store_slogan', 'Paixão que se veste')) ?></span>
                    </span>
                </a>
                <p><?= e(setting('about_text', 'Camisas, chuteiras e acessórios para quem vive o futebol.')) ?></p>
            </div>

            <div class="footer-col">
                <h3>Loja</h3>
                <ul>
                    <li><a href="<?= e(url('/catalogo')) ?>">Catálogo completo</a></li>
                    <?php foreach (array_slice($footerCategories, 0, 4) as $footerCategory): ?>
                        <li><a href="<?= e(url('/catalogo?category=' . urlencode((string) $footerCategory['slug']))) ?>"><?= e($footerCategory['name']) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?= e(url('/catalogo?sort=newest')) ?>">Novidades</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h3>Institucional</h3>
                <ul>
                    <li><a href="<?= e(url('/sobre')) ?>">Sobre a loja</a></li>
                    <li><a href="<?= e(url('/faq')) ?>">Perguntas frequentes</a></li>
                    <li><a href="<?= e(url('/contato')) ?>">Contato</a></li>
                    <li><a href="<?= e(url('/minha-conta/pedidos')) ?>">Meus pedidos</a></li>
                    <li><a href="<?= e(url('/minha-conta')) ?>">Minha conta</a></li>
                </ul>
            </div>

            <div class="footer-col footer-col--contact">
                <h3>Atendimento</h3>
                <ul class="footer-contact">
                    <?php if (setting('contact_email', '') !== ''): ?>
                        <li>✉️ <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li>
                    <?php endif; ?>
                    <?php if (setting('contact_phone', '') !== ''): ?>
                        <li>📞 <?= e(setting('contact_phone')) ?></li>
                    <?php endif; ?>
                    <?php if (setting('whatsapp', '') !== ''): ?>
                        <li>💬 <a href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) setting('whatsapp'))) ?>" target="_blank" rel="noopener">WhatsApp</a></li>
                    <?php endif; ?>
                    <?php if (setting('address', '') !== ''): ?>
                        <li>📍 <?= e(setting('address')) ?></li>
                    <?php endif; ?>
                    <?php if (setting('instagram', '') !== ''): ?>
                        <li>📷 <?= e(setting('instagram')) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(setting('footer_text', setting('store_name', 'Simês de Fútbol'))) ?></span>
            <div class="footer-bottom__badges">
                <span>Compra segura</span>
                <span>Dados protegidos</span>
                <span>Troca fácil</span>
            </div>
        </div>
    </div>
</footer>
