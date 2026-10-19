<?php

declare(strict_types=1);

namespace App\Http;

use ErrorException;
use App\Domain\ReservaNotFoundException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $error): void {
            restore_error_handler();
            self::respond($error);
        });
    }

    public static function isApiRequest(): bool
    {
        $path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
        return $path === '/api' || str_starts_with($path, '/api/');
    }

    public static function respond(Throwable $error): void
    {
        if (!self::isApiRequest()) {
            $status = 500;
            $message = 'Error interno del servidor';
            if ($error instanceof ReservaNotFoundException) {
                $status = 404;
                $message = 'Reserva no encontrada';
            } elseif ($error instanceof RequestException) {
                $status = $error->status;
                $message = $error->getMessage();
            } else {
                error_log('MisterPlan: excepción inesperada de tipo ' . get_class($error));
            }
            try {
                HtmlResponse::create()->error($message, $status);
            } catch (Throwable $renderError) {
                HtmlResponse::fallback($status);
            }
            return;
        }
        if ($error instanceof RequestException) {
            JsonResponse::send(['error' => $error->getMessage()], $error->status);
            return;
        }
        if ($error instanceof \App\Domain\ReservaValidationException) {
            JsonResponse::send(['error' => $error->getMessage(), 'errors' => $error->errors], 422);
            return;
        }
        if ($error instanceof ReservaNotFoundException) {
            JsonResponse::send(['error' => 'Reserva no encontrada'], 404);
            return;
        }
        if ($error instanceof \App\Domain\ReservaConflictException) {
            JsonResponse::send(['error' => $error->getMessage()], 409);
            return;
        }
        error_log('MisterPlan: excepción inesperada de tipo ' . get_class($error));
        JsonResponse::send(['error' => 'Error interno del servidor'], 500);
    }
}
