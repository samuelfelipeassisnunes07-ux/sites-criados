<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = self::path($view);
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View não encontrada: ' . $view);
        }

        $content = self::capture($viewFile, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = self::path($layout);
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout não encontrado: ' . $layout);
        }

        echo self::capture($layoutFile, array_merge($data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    public static function exists(string $view): bool
    {
        return is_file(self::path($view));
    }

    private static function path(string $view): string
    {
        return BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';
    }
}
