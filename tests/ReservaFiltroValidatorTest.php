<?php

declare(strict_types=1);

use App\Validation\ReservaFiltroValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservaFiltroValidatorTest extends TestCase
{
    #[DataProvider('validFilters')]
    public function testValidFilters(array $input): void
    {
        $original = $input;
        self::assertSame([], (new ReservaFiltroValidator())->validate($input));
        self::assertSame($original, $input);
    }

    public static function validFilters(): iterable
    {
        yield [[]];
        yield [['estado' => 'confirmada', 'desde' => '2024-02-29', 'hasta' => '2024-02-29', 'q' => ' Muñoz ', 'page' => '2', 'per_page' => '100']];
        yield [['estado' => '', 'desde' => '', 'hasta' => '', 'q' => '   ']];
        yield [['desde' => '1000-01-01']];
        yield [['hasta' => '9999-12-31']];
        yield [['q' => ' ' . str_repeat('ñ', 100) . ' ']];
        yield [['page' => (string) PHP_INT_MAX, 'per_page' => '1']];
    }

    #[DataProvider('invalidFilters')]
    public function testInvalidFilters(string $field, mixed $value): void
    {
        self::assertArrayHasKey($field, (new ReservaFiltroValidator())->validate([$field => $value]));
    }

    public static function invalidFilters(): iterable
    {
        foreach (['estado', 'desde', 'hasta', 'q', 'page', 'per_page'] as $field) {
            foreach ([[], null, new stdClass(), true, 1, 1.5] as $value) {
                yield [$field, $value];
            }
        }
        yield ['estado', 'desconocido'];
        yield ['desconocido', 'valor'];
        yield ['q', str_repeat('ñ', 101)];
        yield ['q', "\xFF"];
        foreach (['desde', 'hasta'] as $field) {
            foreach (['2023-02-29', "2024-02-29\0", '0999-01-01', '10000-01-01', '2024-2-29', '2024-02-29 ', "2024-02-29\n"] as $date) {
                yield [$field, $date];
            }
        }
        foreach (['page', 'per_page'] as $field) {
            foreach (['', '0', '-1', '+1', '1.0', '1e2', '1 ', "1\n", str_repeat('9', 100)] as $number) {
                yield [$field, $number];
            }
        }
        yield ['per_page', '101'];
    }

    public function testInvertedRangeAndOffsetOverflow(): void
    {
        $validator = new ReservaFiltroValidator();
        self::assertArrayHasKey('hasta', $validator->validate(['desde' => '2024-03-01', 'hasta' => '2024-02-29']));
        self::assertArrayHasKey('page', $validator->validate(['page' => (string) PHP_INT_MAX]));
    }
}
