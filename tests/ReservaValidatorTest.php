<?php

declare(strict_types=1);

use App\Validation\ReservaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservaValidatorTest extends TestCase
{
    private static function payload(): array
    {
        return [
            'nombre_huesped' => "María Muñoz O'Neill-Peña",
            'email_huesped' => 'maria@example.com',
            'nombre_alojamiento' => 'Cabaña del río',
            'fecha_entrada' => '2024-02-29',
            'fecha_salida' => '2024-03-01',
            'importe' => '120.50',
        ];
    }

    #[DataProvider('validChanges')]
    public function testValidPayloads(array $changes): void
    {
        $input = array_replace(self::payload(), $changes);
        $original = $input;
        self::assertSame([], (new ReservaValidator())->validateCreate($input));
        self::assertSame($original, $input);
    }

    public static function validChanges(): iterable
    {
        yield 'notas ausentes y fechas pasadas' => [[]];
        foreach ([null, '', "Primera línea\r\nSegunda\tlínea", str_repeat('ñ', 5000)] as $notes) {
            yield [['notas' => $notes]];
        }
        foreach (['nombre_huesped', 'nombre_alojamiento'] as $field) {
            yield [[$field => str_repeat('ñ', 150)]];
        }
        yield [['email_huesped' => str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 61)]];
        yield [['email_huesped' => ' maria@example.com ']];
        foreach ([0, 42, 99999999, '0', '0.00', '99999999.99', '00099999999.99', '12.5'] as $amount) {
            yield [['importe' => $amount]];
        }
        yield [['fecha_entrada' => '1000-01-01', 'fecha_salida' => '9999-12-31']];
        yield [['nombre_huesped' => '<b>María</b>']];
    }

    #[DataProvider('invalidChanges')]
    public function testInvalidPayloads(string $field, mixed $value): void
    {
        $input = array_replace(self::payload(), [$field => $value]);
        $original = $input;
        $errors = (new ReservaValidator())->validateCreate($input);
        self::assertSame([$field], array_keys($errors));
        self::assertIsString($errors[$field]);
        self::assertNotSame('', $errors[$field]);
        self::assertSame($original, $input);
    }

    public static function invalidChanges(): iterable
    {
        foreach (array_keys(self::payload()) as $field) {
            foreach ([null, '', '   ', [], new stdClass(), true, false, 1.5] as $value) {
                yield [$field, $value];
            }
        }
        foreach (['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'fecha_entrada', 'fecha_salida', 'notas'] as $field) {
            yield [$field, 123];
        }
        foreach (['nombre_huesped' => 150, 'nombre_alojamiento' => 150, 'email_huesped' => 254, 'notas' => 5000] as $field => $limit) {
            yield [$field, str_repeat('ñ', $limit + 1)];
            yield [$field, "Texto\xFF"];
            yield [$field, "Texto\0"];
            yield [$field, "Texto\x7F"];
            yield [$field, "Texto\u{0085}"];
        }
        foreach (['nombre_huesped', 'nombre_alojamiento', 'email_huesped'] as $field) {
            yield [$field, "Texto\n"];
            yield [$field, "Texto\t"];
        }
        yield ['email_huesped', 'correo-invalido'];
        foreach (['2023-02-29', '2024-02-30', '2024-13-01', '2024-00-01', '2024-01-00', '0999-12-31', '0000-01-01', '10000-01-01', "2024-02-29\0", "2024-02-29\n", ' 2024-02-29', '2024-02-29 ', '2024-02-29T00:00:00', '2024-2-29'] as $date) {
            foreach (['fecha_entrada', 'fecha_salida'] as $field) {
                yield [$field, $date];
            }
        }
        yield ['fecha_salida', '2024-02-29'];
        yield ['fecha_salida', '2024-02-28'];
        foreach ([-1, '-0', '-1.00', '+1', '100000000', '100000000.00', '1.234', 0.0, 12.5, '1e2', '1,20', '1,000.00', '1.000,00', '1.', '.50', "12.00\n", "12.00\0", '12 euros', ' 12', '12 ', str_repeat('9', 100)] as $amount) {
            yield ['importe', $amount];
        }
        foreach ([[], new stdClass(), true, false, "Notas\x0B", "Notas\x1B"] as $notes) {
            yield ['notas', $notes];
        }
        foreach (['id', 'estado', 'fecha_creacion', 'desconocido'] as $field) {
            yield [$field, null];
        }
    }

    public function testAllRequiredFieldsAreReported(): void
    {
        self::assertSame(array_keys(self::payload()), array_keys((new ReservaValidator())->validateCreate([])));
        foreach (array_keys(self::payload()) as $field) {
            $input = self::payload();
            unset($input[$field]);
            self::assertSame([$field], array_keys((new ReservaValidator())->validateCreate($input)));
        }
    }

    public function testMessagesDoNotExposeInput(): void
    {
        $secret = 'dato-privado-no-valido';
        foreach ((new ReservaValidator())->validateCreate(['email_huesped' => $secret, $secret => $secret]) as $message) {
            self::assertStringNotContainsString($secret, $message);
        }
    }
}
