<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        Response::redirect(url($path));
    }

    protected function success(string $message): void
    {
        Session::flash('success', $message);
    }

    protected function error(string $message): void
    {
        Session::flash('error', $message);
    }

    /** @param array<string,mixed> $data */
    protected function renderAdmin(string $view, array $data = []): void
    {
        $this->render($view, $data, 'layouts/admin');
    }
}
