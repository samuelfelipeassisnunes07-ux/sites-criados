<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\User;

final class MailService
{
    public static function sendPasswordReset(array $user, string $resetUrl): bool
    {
        $name = e($user['name'] ?? 'cliente');
        $body = '<p>Olá, ' . $name . '!</p>'
            . '<p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>'
            . '<p><a href="' . e($resetUrl) . '" style="display:inline-block;padding:12px 20px;background:#0b6e4f;color:#fff;text-decoration:none;border-radius:8px">Redefinir minha senha</a></p>'
            . '<p>Se o botão não funcionar, copie e cole este link no navegador:<br><a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p>'
            . '<p>Este link expira em 60 minutos e só pode ser usado uma vez. Se você não solicitou, ignore este e-mail.</p>';

        return self::send(
            (string) $user['email'],
            'Redefinição de senha - ' . (string) Config::get('app.name', 'Loja'),
            self::wrap('Redefinição de senha', $body),
            "Redefina sua senha acessando:\n" . $resetUrl
        );
    }

    /**
     * @param array<string,mixed> $order
     * @param array<int,array<string,mixed>> $items
     */
    public static function sendOrderCreated(array $order, array $items): bool
    {
        $user = User::find((int) $order['user_id']);
        if ($user === null) {
            return false;
        }

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td style="padding:8px;border-bottom:1px solid #eee">' . e($item['product_name']) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center">' . (int) $item['quantity'] . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right">' . money((float) $item['line_total']) . '</td>'
                . '</tr>';
        }

        $body = '<p>Olá, ' . e($user['name']) . '!</p>'
            . '<p>Seu pedido <strong>' . e($order['code']) . '</strong> foi recebido em ' . e(format_datetime($order['created_at'])) . '.</p>'
            . '<table style="width:100%;border-collapse:collapse"><thead><tr>'
            . '<th style="text-align:left;padding:8px;border-bottom:2px solid #ddd">Produto</th>'
            . '<th style="padding:8px;border-bottom:2px solid #ddd">Qtd.</th>'
            . '<th style="text-align:right;padding:8px;border-bottom:2px solid #ddd">Total</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p style="text-align:right">Subtotal: ' . money((float) $order['subtotal']) . '<br>'
            . 'Desconto: -' . money((float) $order['discount_total']) . '<br>'
            . 'Frete: ' . money((float) $order['shipping_total']) . '<br>'
            . '<strong>Total: ' . money((float) $order['total']) . '</strong></p>'
            . '<p>Status atual: <strong>' . e(status_label($order['status'])) . '</strong></p>'
            . '<p><a href="' . e(absolute_url('/minha-conta/pedidos/' . $order['code'])) . '">Acompanhar meu pedido</a></p>';

        return self::send(
            (string) $user['email'],
            'Pedido ' . (string) $order['code'] . ' recebido',
            self::wrap('Recebemos seu pedido', $body),
            'Pedido ' . $order['code'] . ' no valor de ' . money((float) $order['total'])
        );
    }

    /** @param array<string,mixed> $order */
    public static function sendOrderStatus(array $order): bool
    {
        $user = User::find((int) $order['user_id']);
        if ($user === null) {
            return false;
        }

        $body = '<p>Olá, ' . e($user['name']) . '!</p>'
            . '<p>O pedido <strong>' . e($order['code']) . '</strong> foi atualizado para: '
            . '<strong>' . e(status_label($order['status'])) . '</strong>.</p>';

        if (!empty($order['tracking_code'])) {
            $body .= '<p>Código de rastreamento: <strong>' . e($order['tracking_code']) . '</strong></p>';
        }

        $body .= '<p><a href="' . e(absolute_url('/minha-conta/pedidos/' . $order['code'])) . '">Ver detalhes do pedido</a></p>';

        return self::send(
            (string) $user['email'],
            'Atualização do pedido ' . (string) $order['code'],
            self::wrap('Seu pedido foi atualizado', $body),
            'Pedido ' . $order['code'] . ' agora está: ' . status_label($order['status'])
        );
    }

    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
        $driver = (string) Config::get('app.mail_driver', 'log');

        if ($driver === 'log') {
            return self::log($to, $subject, $html, $text);
        }

        $from = (string) Config::get('app.mail_from', 'no-reply@localhost');
        $fromName = (string) Config::get('app.mail_from_name', 'Loja');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        return @mail($to, self::encodeHeader($subject), $html, implode("\r\n", $headers));
    }

    private static function log(string $to, string $subject, string $html, string $text): bool
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $body = $text !== '' ? $text : strip_tags(str_replace(['<br>', '</p>'], "\n", $html));
        $entry = sprintf(
            "[%s]\nTO: %s\nSUBJECT: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('-', 60),
            $body
        );

        return @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function wrap(string $title, string $body): string
    {
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f5;font-family:Arial,Helvetica,sans-serif;color:#1f2d2a">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px">'
            . '<div style="background:#0b6e4f;color:#fff;padding:18px 24px;border-radius:12px 12px 0 0;font-size:20px;font-weight:bold">'
            . e((string) Config::get('app.name', 'Loja'))
            . '</div>'
            . '<div style="background:#fff;padding:24px;border-radius:0 0 12px 12px;line-height:1.6">' . $body . '</div>'
            . '<p style="text-align:center;color:#888;font-size:12px;margin-top:16px">E-mail automático — não responda.</p>'
            . '</div></body></html>';
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
