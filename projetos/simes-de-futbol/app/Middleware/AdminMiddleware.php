<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Session::set('intended_url', Request::path());
            Session::flash('error', 'Faça login para acessar o painel administrativo.');
            Response::redirect(url('/login'));
        }

        if (!Auth::isAdmin()) {
            Logger::security('Tentativa de acesso administrativo negada', [
                'user_id' => Auth::id(),
                'path' => Request::path(),
                'ip' => Request::ip(),
            ]);
            Response::abort(403, 'Esta área é restrita a administradores.');
        }
    }
}
