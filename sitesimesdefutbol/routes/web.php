<?php

declare(strict_types=1);

use App\Controllers\Admin\AdminBannerController;
use App\Controllers\Admin\AdminCategoryController;
use App\Controllers\Admin\AdminCouponController;
use App\Controllers\Admin\AdminCustomerController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminLogController;
use App\Controllers\Admin\AdminOrderController;
use App\Controllers\Admin\AdminProductController;
use App\Controllers\Admin\AdminReportController;
use App\Controllers\Admin\AdminSettingController;
use App\Controllers\Store\AccountController;
use App\Controllers\Store\AuthController;
use App\Controllers\Store\CartController;
use App\Controllers\Store\CatalogController;
use App\Controllers\Store\CheckoutController;
use App\Controllers\Store\FavoriteController;
use App\Controllers\Store\HomeController;
use App\Controllers\Store\OrderController;
use App\Controllers\Store\PageController;
use App\Controllers\Store\ProductController;
use App\Controllers\Store\WebhookController;
use App\Core\Router;

return static function (Router $router): void {
    // ---------------- Loja ----------------
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/catalogo', [CatalogController::class, 'index']);
    $router->get('/produto/{slug}', [ProductController::class, 'show']);

    // Carrinho (visitante e cliente)
    $router->get('/carrinho', [CartController::class, 'index']);
    $router->post('/carrinho/adicionar', [CartController::class, 'add']);
    $router->post('/carrinho/atualizar', [CartController::class, 'update']);
    $router->post('/carrinho/remover', [CartController::class, 'remove']);
    $router->post('/carrinho/limpar', [CartController::class, 'clear']);

    // Favoritos (somente cliente logado)
    $router->get('/favoritos', [FavoriteController::class, 'index'], ['auth']);
    $router->post('/favoritos/alternar', [FavoriteController::class, 'toggle'], ['auth']);

    // Checkout e pedidos do cliente
    $router->get('/checkout', [CheckoutController::class, 'index'], ['auth']);
    $router->post('/checkout', [CheckoutController::class, 'store'], ['auth']);
    $router->get('/pedido/{code}/confirmacao', [OrderController::class, 'confirmation'], ['auth']);
    $router->post('/pedido/{code}/simular-pagamento', [OrderController::class, 'simulatePayment'], ['auth']);
    $router->get('/pedido/{code}/pagamento', [OrderController::class, 'retryPayment'], ['auth']);

    // Autenticação
    $router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
    $router->post('/login', [AuthController::class, 'login'], ['guest']);
    $router->get('/cadastro', [AuthController::class, 'showRegister'], ['guest']);
    $router->post('/cadastro', [AuthController::class, 'register'], ['guest']);
    $router->post('/logout', [AuthController::class, 'logout'], ['auth']);
    $router->get('/esqueci-senha', [AuthController::class, 'showForgot'], ['guest']);
    $router->post('/esqueci-senha', [AuthController::class, 'sendReset'], ['guest']);
    $router->get('/redefinir-senha', [AuthController::class, 'showReset'], ['guest']);
    $router->post('/redefinir-senha', [AuthController::class, 'reset'], ['guest']);

    // Minha conta
    $router->get('/minha-conta', [AccountController::class, 'index'], ['auth']);
    $router->post('/minha-conta/perfil', [AccountController::class, 'updateProfile'], ['auth']);
    $router->post('/minha-conta/senha', [AccountController::class, 'updatePassword'], ['auth']);
    $router->get('/minha-conta/enderecos', [AccountController::class, 'addresses'], ['auth']);
    $router->get('/minha-conta/enderecos/novo', [AccountController::class, 'createAddress'], ['auth']);
    $router->get('/minha-conta/enderecos/{id}/editar', [AccountController::class, 'editAddress'], ['auth']);
    $router->post('/minha-conta/enderecos', [AccountController::class, 'storeAddress'], ['auth']);
    $router->post('/minha-conta/enderecos/{id}', [AccountController::class, 'updateAddress'], ['auth']);
    $router->post('/minha-conta/enderecos/{id}/excluir', [AccountController::class, 'deleteAddress'], ['auth']);
    $router->post('/minha-conta/enderecos/{id}/padrao', [AccountController::class, 'setDefaultAddress'], ['auth']);
    $router->get('/minha-conta/pedidos', [AccountController::class, 'orders'], ['auth']);
    $router->get('/minha-conta/pedidos/{code}', [AccountController::class, 'orderShow'], ['auth']);

    // Institucional
    $router->get('/sobre', [PageController::class, 'about']);
    $router->get('/contato', [PageController::class, 'contact']);
    $router->post('/contato', [PageController::class, 'sendContact']);
    $router->get('/faq', [PageController::class, 'faq']);

    // Webhooks de pagamento (sem CSRF; autenticados por assinatura do provedor)
    $router->post('/webhooks/mercadopago', [WebhookController::class, 'mercadopago'], ['no-csrf']);

    // ---------------- Painel administrativo ----------------
    $admin = ['auth', 'admin'];

    $router->get('/admin', [AdminDashboardController::class, 'index'], $admin);

    // Produtos
    $router->get('/admin/produtos', [AdminProductController::class, 'index'], $admin);
    $router->get('/admin/produtos/novo', [AdminProductController::class, 'create'], $admin);
    $router->post('/admin/produtos', [AdminProductController::class, 'store'], $admin);
    $router->get('/admin/produtos/{id}/editar', [AdminProductController::class, 'edit'], $admin);
    $router->post('/admin/produtos/{id}', [AdminProductController::class, 'update'], $admin);
    $router->post('/admin/produtos/{id}/ativar', [AdminProductController::class, 'toggle'], $admin);
    $router->post('/admin/produtos/{id}/excluir', [AdminProductController::class, 'destroy'], $admin);
    $router->post('/admin/produtos/{id}/imagens', [AdminProductController::class, 'uploadImages'], $admin);
    $router->post('/admin/produtos/{product_id}/imagens/{image_id}/excluir', [AdminProductController::class, 'deleteImage'], $admin);
    $router->post('/admin/produtos/{product_id}/imagens/{image_id}/principal', [AdminProductController::class, 'primaryImage'], $admin);
    $router->post('/admin/produtos/{id}/estoque', [AdminProductController::class, 'adjustStock'], $admin);

    // Categorias
    $router->get('/admin/categorias', [AdminCategoryController::class, 'index'], $admin);
    $router->post('/admin/categorias', [AdminCategoryController::class, 'store'], $admin);
    $router->post('/admin/categorias/{id}', [AdminCategoryController::class, 'update'], $admin);
    $router->post('/admin/categorias/{id}/ativar', [AdminCategoryController::class, 'toggle'], $admin);
    $router->post('/admin/categorias/{id}/excluir', [AdminCategoryController::class, 'destroy'], $admin);

    // Cupons
    $router->get('/admin/cupons', [AdminCouponController::class, 'index'], $admin);
    $router->post('/admin/cupons', [AdminCouponController::class, 'store'], $admin);
    $router->post('/admin/cupons/{id}', [AdminCouponController::class, 'update'], $admin);
    $router->post('/admin/cupons/{id}/ativar', [AdminCouponController::class, 'toggle'], $admin);
    $router->post('/admin/cupons/{id}/excluir', [AdminCouponController::class, 'destroy'], $admin);

    // Banners
    $router->get('/admin/banners', [AdminBannerController::class, 'index'], $admin);
    $router->post('/admin/banners', [AdminBannerController::class, 'store'], $admin);
    $router->post('/admin/banners/{id}', [AdminBannerController::class, 'update'], $admin);
    $router->post('/admin/banners/{id}/ativar', [AdminBannerController::class, 'toggle'], $admin);
    $router->post('/admin/banners/{id}/excluir', [AdminBannerController::class, 'destroy'], $admin);

    // Pedidos
    $router->get('/admin/pedidos', [AdminOrderController::class, 'index'], $admin);
    $router->get('/admin/pedidos/{id}', [AdminOrderController::class, 'show'], $admin);
    $router->post('/admin/pedidos/{id}/status', [AdminOrderController::class, 'updateStatus'], $admin);
    $router->post('/admin/pedidos/{id}/rastreio', [AdminOrderController::class, 'addTracking'], $admin);
    $router->post('/admin/pedidos/{id}/cancelar', [AdminOrderController::class, 'cancel'], $admin);

    // Clientes
    $router->get('/admin/clientes', [AdminCustomerController::class, 'index'], $admin);
    $router->get('/admin/clientes/{id}', [AdminCustomerController::class, 'show'], $admin);
    $router->post('/admin/clientes/{id}/bloquear', [AdminCustomerController::class, 'toggleBlock'], $admin);

    // Relatórios
    $router->get('/admin/relatorios', [AdminReportController::class, 'index'], $admin);
    $router->get('/admin/relatorios/exportar', [AdminReportController::class, 'exportCsv'], $admin);

    // Configurações e logs
    $router->get('/admin/configuracoes', [AdminSettingController::class, 'edit'], $admin);
    $router->post('/admin/configuracoes', [AdminSettingController::class, 'update'], $admin);
    $router->get('/admin/logs', [AdminLogController::class, 'index'], $admin);
};
