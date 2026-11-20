<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }
        exit;
    }

    public static function back(): never
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '' && str_starts_with($referer, (string) Config::get('app.url', ''))) {
            self::redirect($referer);
        }

        self::redirect(url('/'));
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);

        $titles = [
            400 => 'Requisição inválida',
            401 => 'Autenticação necessária',
            403 => 'Acesso negado',
            404 => 'Página não encontrada',
            405 => 'Método não permitido',
            419 => 'Sessão expirada',
            429 => 'Muitas tentativas',
            500 => 'Erro interno',
            503 => 'Serviço indisponível',
        ];
        $title = $titles[$status] ?? 'Erro';

        try {
            View::render('errors/error', [
                'status' => $status,
                'title' => $title,
                'message' => $message !== '' ? $message : 'Não foi possível concluir a operação.',
            ], 'layouts/app');
        } catch (\Throwable) {
            echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>' . $status . '</title>'
                . '<body style="font-family:sans-serif;padding:2rem"><h1>' . $status . ' - ' . e($title) . '</h1>'
                . '<p>' . e($message) . '</p><p><a href="' . e(url('/')) . '">Voltar para a loja</a></p></body></html>';
        }

        exit;
    }

    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header(
            "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-inline'; font-src 'self' data:; form-action 'self'; frame-ancestors 'self'; base-uri 'self'"
        );
    }
}
