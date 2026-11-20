<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;

final class Router
{
    /** @var list<array{method:string,pattern:string,handler:array{0:class-string,1:string}|callable,middleware:list<string>}> */
    private array $routes = [];

    public function get(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[$route['method']] = true;
                continue;
            }

            $isUnsafe = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
            if ($isUnsafe && !in_array('no-csrf', $route['middleware'], true)) {
                if (!Csrf::verify(is_string(Request::input('_token')) ? Request::input('_token') : null)) {
                    Logger::security('Token CSRF inválido', ['path' => $path, 'ip' => Request::ip()]);
                    Response::abort(419, 'Sua sessão expirou. Atualize a página e tente novamente.');
                }
            }

            foreach ($route['middleware'] as $middleware) {
                if ($middleware === 'no-csrf') {
                    continue;
                }
                $this->runMiddleware($middleware);
            }

            $handler = $route['handler'];
            if (is_callable($handler)) {
                $handler($params);
            } else {
                [$class, $methodName] = $handler;
                $controller = new $class();
                $controller->{$methodName}($params);
            }

            return;
        }

        if ($allowed !== []) {
            Response::abort(405, 'Método HTTP não permitido para esta rota.');
        }

        Response::abort(404, 'A página que você procura não existe ou foi movida.');
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $names = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$names): string {
                $names[] = $m[1];
                return '([^/]+)';
            },
            $pattern
        );

        if (!is_string($regex) || !preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }

        array_shift($matches);
        $params = [];
        foreach ($names as $index => $name) {
            $params[$name] = rawurldecode((string) ($matches[$index] ?? ''));
        }

        return $params;
    }

    private function runMiddleware(string $name): void
    {
        $map = [
            'auth' => AuthMiddleware::class,
            'guest' => GuestMiddleware::class,
            'admin' => AdminMiddleware::class,
        ];

        if (!isset($map[$name])) {
            throw new \LogicException('Middleware desconhecido: ' . $name);
        }

        $middleware = new $map[$name]();
        $middleware->handle();
    }
}
