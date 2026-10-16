<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][$method] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        // Conservar literalmente la ruta, incluida su barra final.
        $path = explode('?', $uri, 2)[0];
        $handlers = $this->routes[$path] ?? null;
        if ($handlers === null) {
            JsonResponse::send(['error' => 'Ruta no encontrada'], 404);
            return;
        }

        $handler = $handlers[$method] ?? ($method === 'HEAD' ? ($handlers['GET'] ?? null) : null);
        if ($handler === null) {
            $allowed = array_keys($handlers);
            if (isset($handlers['GET']) && !isset($handlers['HEAD'])) {
                $allowed[] = 'HEAD';
            }
            JsonResponse::send(['error' => 'Método no permitido'], 405, [
                'Allow' => implode(', ', $allowed),
            ]);
            return;
        }

        // Retener la salida también evita enviar HTML parcial si falla el manejador.
        ob_start();
        try {
            $handler();
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if ($method !== 'HEAD') {
            echo $body;
        }
    }
}
