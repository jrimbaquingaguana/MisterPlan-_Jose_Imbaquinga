<?php

declare(strict_types=1);

namespace App\Http;

use ErrorException;
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
            // No registrar mensajes ni trazas: pueden contener datos sensibles.
            error_log('MisterPlan: excepción inesperada de tipo ' . get_class($error));
            JsonResponse::send(['error' => 'Error interno del servidor'], 500);
        });
    }
}
