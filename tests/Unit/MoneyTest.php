<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_normalize_two_decimal_places(): void
    {
        $this->assertSame('150.50', Money::normalize('150.5'));
        $this->assertSame('150.50', Money::normalize(150.5));
        $this->assertSame('0.00', Money::normalize(null));
    }

    public function test_add_avoids_float_drift(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('100.00', Money::add('99.99', '0.01'));
    }

    public function test_mul_line_total(): void
    {
        $this->assertSame('640.00', Money::mul('320.00', 2));
        $this->assertSame('97.50', Money::mul('32.50', 3));
    }

    public function test_percentage_vat_rounding(): void
    {
        $this->assertSame('20.00', Money::percentage('100.00', '20'));
        $this->assertSame('18.15', Money::percentage('90.75', '20'));
    }

    public function test_sum_multiple_amounts(): void
    {
        $this->assertSame('375.00', Money::sum(['320.00', '40.00', '15.00']));
    }
}
