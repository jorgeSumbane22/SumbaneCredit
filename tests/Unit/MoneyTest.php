<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('roundingCases')]
    public function test_rounds_half_up_away_from_zero(string $value, string $expected): void
    {
        $this->assertSame($expected, Money::round($value));
    }

    public static function roundingCases(): array
    {
        return [
            ['1.005', '1.01'],
            ['1.004999', '1.00'],
            ['2.675', '2.68'],     // falha com float (2.67)
            ['-1.005', '-1.01'],
            ['-0.001', '0.00'],
            ['0', '0.00'],
            ['1234567.895', '1234567.90'],
        ];
    }

    public function test_decimal_arithmetic_has_no_float_errors(): void
    {
        $this->assertSame('0.3', Money::add('0.1', '0.2'));
        $this->assertTrue(Money::eq(Money::add('0.1', '0.2'), '0.3'));
        $this->assertSame('100', Money::sum(['33.33', '33.33', '33.34']));
    }

    public function test_formats_mozambican_metical(): void
    {
        $this->assertSame('1.234.567,89 MZN', Money::format('1234567.891'));
        $this->assertSame('0,00 MZN', Money::format(null));
        $this->assertSame('-50,00', Money::format('-50', false));
    }

    public function test_parses_user_input(): void
    {
        $this->assertSame('1234.56', Money::parseInput('1.234,56'));
        $this->assertSame('1234.56', Money::parseInput('1234.56'));
        $this->assertSame('1234.5', Money::parseInput('1 234,5 MZN'));
        $this->assertNull(Money::parseInput('abc'));
    }

    public function test_rejects_invalid_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::add('12abc', '1');
    }

    public function test_division_by_zero_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::div('10', '0');
    }
}
