<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public function run(): void
    {
        Response::securityHeaders();

        $router = new Router();
        $registerRoutes = require BASE_PATH . '/routes/web.php';

        if (!is_callable($registerRoutes)) {
            throw new \RuntimeException('Arquivo de rotas inválido.');
        }

        $registerRoutes($router);

        try {
            $router->dispatch(Request::method(), Request::path());
        } catch (HttpException $e) {
            Response::abort($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Exceção não tratada: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $message = Config::get('app.debug')
                ? 'Detalhe técnico: ' . $e->getMessage()
                : 'Ocorreu um erro inesperado. Nossa equipe foi notificada.';

            Response::abort(500, $message);
        }
    }
}
