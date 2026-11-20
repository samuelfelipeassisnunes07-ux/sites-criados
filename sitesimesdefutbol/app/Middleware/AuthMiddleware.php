<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            return;
        }

        Session::set('intended_url', Request::path());
        Session::flash('error', 'Faça login para acessar esta área.');
        Response::redirect(url('/login'));
    }
}
