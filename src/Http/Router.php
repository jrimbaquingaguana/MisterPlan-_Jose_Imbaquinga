<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    private array $routes = [];
    private array $dynamicRoutes = [];

    public function __construct(private ?\Closure $renderError = null)
    {
    }

    public function add(string $method, string $path, callable $handler): void
    {
        if (str_contains($path, '/{id}')) {
            $this->dynamicRoutes[$path][$method] = $handler;
            return;
        }
        $this->routes[$path][$method] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = explode('?', $uri, 2)[0];
        $handlers = $this->routes[$path] ?? null;
        $arguments = [];
        if ($handlers === null) {
            foreach ($this->dynamicRoutes as $route => $registered) {
                [$prefix, $suffix] = explode('{id}', $route, 2);
                if (str_starts_with($path, $prefix) && str_ends_with($path, $suffix)) {
                    $id = substr($path, strlen($prefix), strlen($path) - strlen($prefix) - strlen($suffix));
                    if ($id !== '' && !str_contains($id, '/')) {
                        $handlers = $registered;
                        $arguments = [$id];
                        break;
                    }
                }
            }
        }
        if ($handlers === null) {
            $this->error('Ruta no encontrada', 404);
            return;
        }

        $handler = $handlers[$method] ?? ($method === 'HEAD' ? ($handlers['GET'] ?? null) : null);
        if ($handler === null) {
            $allowed = array_keys($handlers);
            if (isset($handlers['GET']) && !isset($handlers['HEAD'])) {
                $allowed[] = 'HEAD';
            }
            $this->error('Método no permitido', 405, [
                'Allow' => implode(', ', $allowed),
            ]);
            return;
        }

        ob_start();
        try {
            $handler(...$arguments);
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if ($method !== 'HEAD') {
            echo $body;
        }
    }

    private function error(string $message, int $status, array $headers = []): void
    {
        if ($this->renderError !== null) {
            ($this->renderError)($message, $status, $headers);
            return;
        }
        JsonResponse::send(['error' => $message], $status, $headers);
    }
}
