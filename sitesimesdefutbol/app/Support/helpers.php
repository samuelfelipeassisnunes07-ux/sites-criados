<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $basePath = parse_url((string) config('app.url', ''), PHP_URL_PATH) ?: '';
        $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');

        if ($path === '') {
            return $basePath === '' ? '/' : $basePath . '/';
        }

        return $basePath . '/' . ltrim($path, '/');
    }
}

if (!function_exists('absolute_url')) {
    function absolute_url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $publicUrl = trim((string) config('app.public_url', ''));
        if ($publicUrl !== '') {
            return rtrim($publicUrl, '/') . url($path);
        }

        return rtrim((string) config('app.url', ''), '/') . url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Core\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return \App\Core\Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        $old = Session::old($key);

        return $old ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}

if (!function_exists('pull_flashes')) {
    /** @return array<string,mixed> */
    function pull_flashes(): array
    {
        return Session::pullFlashes();
    }
}

if (!function_exists('money')) {
    function money(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd/m/Y'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        try {
            $timezone = new DateTimeZone((string) config('app.timezone', 'America/Sao_Paulo'));

            return (new DateTimeImmutable($date, $timezone))->format($format);
        } catch (\Throwable) {
            return '—';
        }
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $date, string $format = 'd/m/Y H:i'): string
    {
        return format_date($date, $format);
    }
}

if (!function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return match ($status) {
            'aguardando_pagamento' => 'Aguardando pagamento',
            'pago' => 'Pago',
            'em_preparacao' => 'Em preparação',
            'enviado' => 'Enviado',
            'entregue' => 'Entregue',
            'cancelado' => 'Cancelado',
            default => 'Desconhecido',
        };
    }
}

if (!function_exists('status_tone')) {
    function status_tone(?string $status): string
    {
        return match ($status) {
            'aguardando_pagamento' => 'warning',
            'pago' => 'info',
            'em_preparacao' => 'primary',
            'enviado' => 'accent',
            'entregue' => 'success',
            'cancelado' => 'danger',
            default => 'muted',
        };
    }
}

if (!function_exists('payment_status_label')) {
    function payment_status_label(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'paid' => 'Aprovado',
            'failed' => 'Recusado',
            'refunded' => 'Estornado',
            default => '—',
        };
    }
}

if (!function_exists('payment_method_label')) {
    function payment_method_label(?string $method): string
    {
        return match ($method) {
            'demo' => 'Demonstração (simulado)',
            'mercadopago' => 'Mercado Pago',
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'credit_card' => 'Cartão de crédito',
            default => $method !== null && $method !== '' ? ucfirst($method) : '—',
        };
    }
}

if (!function_exists('shipping_method_label')) {
    function shipping_method_label(?string $method): string
    {
        return match ($method) {
            'pickup' => 'Retirar na loja',
            'free' => 'Frete grátis',
            'flat' => 'Entrega padrão',
            default => '—',
        };
    }
}

if (!function_exists('product_price')) {
    /** @param array<string,mixed> $product */
    function product_price(array $product): float
    {
        $price = (float) ($product['price'] ?? 0);
        $sale = $product['sale_price'] ?? null;

        if ($sale !== null && $sale !== '' && (float) $sale > 0 && (float) $sale < $price) {
            return (float) $sale;
        }

        return $price;
    }
}

if (!function_exists('product_old_price')) {
    /** @param array<string,mixed> $product */
    function product_old_price(array $product): ?float
    {
        $price = (float) ($product['price'] ?? 0);
        $sale = $product['sale_price'] ?? null;

        if ($sale !== null && $sale !== '' && (float) $sale > 0 && (float) $sale < $price) {
            return $price;
        }

        return null;
    }
}

if (!function_exists('product_discount_percent')) {
    /** @param array<string,mixed> $product */
    function product_discount_percent(array $product): int
    {
        $old = product_old_price($product);
        if ($old === null) {
            return 0;
        }

        return (int) round((1 - product_price($product) / $old) * 100);
    }
}

if (!function_exists('img_url')) {
    function img_url(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
    {
        if ($path === null || trim($path) === '') {
            return asset($fallback);
        }
        if (preg_match('#^https?://#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, 'assets/')) {
            return asset($path);
        }

        return asset('uploads/' . ltrim($path, '/'));
    }
}

if (!function_exists('paginate_links')) {
    /**
     * @param array<string,mixed> $query Parâmetros extras (filtros) preservados.
     */
    function paginate_links(Paginator $paginator, array $query = []): string
    {
        if (!$paginator->hasPages()) {
            return '';
        }

        $base = url(ltrim(Request::path(), '/'));
        $build = static function (int $page) use ($base, $query): string {
            $params = array_merge($query, ['page' => $page]);

            return $base . '?' . http_build_query($params);
        };

        $current = $paginator->currentPage;
        $last = $paginator->lastPage();
        $html = '<nav class="pagination" aria-label="Paginação"><ul>';

        $html .= '<li><a class="page-link' . ($current <= 1 ? ' is-disabled' : '') . '" href="'
            . e($current <= 1 ? '#' : $build($current - 1)) . '" aria-label="Página anterior">&laquo;</a></li>';

        $start = max(1, $current - 2);
        $end = min($last, $current + 2);

        if ($start > 1) {
            $html .= '<li><a class="page-link" href="' . e($build(1)) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li><span class="page-ellipsis">…</span></li>';
            }
        }

        for ($page = $start; $page <= $end; $page++) {
            $html .= '<li><a class="page-link' . ($page === $current ? ' is-active' : '') . '" href="'
                . e($build($page)) . '">' . $page . '</a></li>';
        }

        if ($end < $last) {
            if ($end < $last - 1) {
                $html .= '<li><span class="page-ellipsis">…</span></li>';
            }
            $html .= '<li><a class="page-link" href="' . e($build($last)) . '">' . $last . '</a></li>';
        }

        $html .= '<li><a class="page-link' . ($current >= $last ? ' is-disabled' : '') . '" href="'
            . e($current >= $last ? '#' : $build($current + 1)) . '" aria-label="Próxima página">&raquo;</a></li>';

        return $html . '</ul></nav>';
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '?';
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}

if (!function_exists('setting')) {
    function setting(string $name, mixed $default = null): mixed
    {
        try {
            $all = \App\Models\Setting::all();
            if (array_key_exists($name, $all) && $all[$name] !== null && $all[$name] !== '') {
                return $all[$name];
            }
        } catch (\Throwable) {
            // Banco indisponível (ex.: tela de erro): usa o padrão.
        }

        return $default;
    }
}

if (!function_exists('auth_user')) {
    /** @return array<string,mixed>|null */
    function auth_user(): ?array
    {
        return \App\Core\Auth::user();
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return \App\Core\Auth::isAdmin();
    }
}

if (!function_exists('is_logged')) {
    function is_logged(): bool
    {
        return \App\Core\Auth::check();
    }
}

if (!function_exists('active_path')) {
    function active_path(string $path, string $class = 'is-active'): string
    {
        $current = Request::path();
        if ($path === '/') {
            return $current === '/' ? $class : '';
        }

        return str_starts_with($current, $path) ? $class : '';
    }
}

if (!function_exists('order_status_steps')) {
    /** @return list<array{status:string,label:string}> */
    function order_status_steps(): array
    {
        return [
            ['status' => 'aguardando_pagamento', 'label' => 'Aguardando pagamento'],
            ['status' => 'pago', 'label' => 'Pago'],
            ['status' => 'em_preparacao', 'label' => 'Em preparação'],
            ['status' => 'enviado', 'label' => 'Enviado'],
            ['status' => 'entregue', 'label' => 'Entregue'],
        ];
    }
}

if (!function_exists('order_status_progress')) {
    function order_status_progress(string $status): int
    {
        $order = ['aguardando_pagamento', 'pago', 'em_preparacao', 'enviado', 'entregue'];
        $index = array_search($status, $order, true);

        return $index === false ? 0 : (int) $index + 1;
    }
}

if (!function_exists('safe_csv')) {
    function safe_csv(mixed $value): string
    {
        $string = (string) $value;
        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@'], true)) {
            return "'" . $string;
        }

        return $string;
    }
}
