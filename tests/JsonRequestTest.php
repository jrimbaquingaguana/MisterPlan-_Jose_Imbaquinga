<?php

declare(strict_types=1);

use App\Http\JsonRequest;
use App\Http\RequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonRequestTest extends TestCase
{
    private function read(string $body, string $type = 'application/json'): array
    {
        $stream = fopen('php://temp', 'w+b');
        try {
            fwrite($stream, $body);
            rewind($stream);
            $result = JsonRequest::read($type, $stream);
            self::assertLessThanOrEqual(JsonRequest::MAX_BYTES + 1, ftell($stream));
            return $result;
        } finally {
            fclose($stream);
        }
    }

    public function testObjectsAndNestedTypesArePreserved(): void
    {
        self::assertSame([], $this->read('{}', 'Application/JSON; charset=utf-8'));
        $value = $this->read('{"nombre_huesped":{},"notas":[]}');
        self::assertInstanceOf(stdClass::class, $value['nombre_huesped']);
        self::assertSame([], $value['notas']);
        self::assertSame([], $this->read('{}' . str_repeat(' ', JsonRequest::MAX_BYTES - 2)));
    }

    #[DataProvider('invalidBodies')]
    public function testInvalidRequests(string $body, string $type, int $status): void
    {
        try {
            $this->read($body, $type);
            self::fail('La petición debería rechazarse.');
        } catch (RequestException $error) {
            self::assertSame($status, $error->status);
        }
    }

    public static function invalidBodies(): iterable
    {
        foreach (['', ' ', '{', '[]', '[{}]', 'null', 'true', '1', '"texto"', "{\"notas\":\"\xFF\"}", str_repeat('{"a":', 40) . '{}' . str_repeat('}', 40)] as $body) {
            yield [$body, 'application/json', 400];
        }
        yield ['{}', 'text/plain', 415];
        yield ['{}', '', 415];
        yield ['{}', 'application/jsonp', 415];
        yield [str_repeat(' ', JsonRequest::MAX_BYTES + 100), 'application/json', 413];
    }
}
