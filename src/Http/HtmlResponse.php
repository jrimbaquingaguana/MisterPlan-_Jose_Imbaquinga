<?php

declare(strict_types=1);

namespace App\Http;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class HtmlResponse
{
    public function __construct(private Environment $twig)
    {
    }

    public static function create(): self
    {
        return new self(new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'), [
            'autoescape' => 'html',
            'strict_variables' => true,
            'cache' => false,
        ]));
    }

    public function render(string $template, array $data, int $status = 200, array $headers = []): void
    {
        $body = $this->twig->render($template, $data);
        self::send($body, $status, $headers);
    }

    public function error(string $message, int $status, array $headers = []): void
    {
        try {
            $this->render('error.html.twig', ['message' => $message, 'status' => $status], $status, $headers);
        } catch (\Throwable $error) {
            self::fallback($status, $headers);
        }
    }

    public static function fallback(int $status = 500, array $headers = []): void
    {
        self::send('<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title>'
            . '<h1>No se pudo completar la solicitud.</h1><p>Vuelve a intentarlo más tarde.</p></html>', $status, $headers);
    }

    private static function send(string $body, int $status, array $headers): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $body;
        }
    }
}
