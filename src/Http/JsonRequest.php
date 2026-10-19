<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;
use stdClass;

final class JsonRequest
{
    public const MAX_BYTES = 65536;

    /** @param resource $stream */
    public static function read(string $contentType, $stream): array
    {
        if (strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new RequestException(415, 'Content-Type debe ser application/json');
        }
        $body = stream_get_contents($stream, self::MAX_BYTES + 1);
        if ($body === false) {
            throw new \RuntimeException('No se pudo leer la petición.');
        }
        if (strlen($body) > self::MAX_BYTES) {
            throw new RequestException(413, 'Cuerpo demasiado grande');
        }
        try {
            $value = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RequestException(400, 'JSON no válido');
        }
        if (!$value instanceof stdClass) {
            throw new RequestException(400, 'El cuerpo debe ser un objeto JSON');
        }
        return get_object_vars($value);
    }
}
