<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Services\RomanNumeralsService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: RomanNumeralsService.
 *
 * Reine Logik-Tests, laufen im Container für einheitliche PHP-Version.
 *
 * @covers \Fisharebest\Webtrees\Services\RomanNumeralsService
 */
class RomanNumeralsIntegrationTest extends MysqlTestCase
{
    private RomanNumeralsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RomanNumeralsService();
    }

    /**
     * @return array<string,array{number:int,roman:string}>
     */
    public static function romanNumeralData(): array
    {
        return [
            '1'    => ['number' => 1, 'roman' => 'I'],
            '4'    => ['number' => 4, 'roman' => 'IV'],
            '5'    => ['number' => 5, 'roman' => 'V'],
            '9'    => ['number' => 9, 'roman' => 'IX'],
            '10'   => ['number' => 10, 'roman' => 'X'],
            '14'   => ['number' => 14, 'roman' => 'XIV'],
            '40'   => ['number' => 40, 'roman' => 'XL'],
            '49'   => ['number' => 49, 'roman' => 'XLIX'],
            '50'   => ['number' => 50, 'roman' => 'L'],
            '90'   => ['number' => 90, 'roman' => 'XC'],
            '100'  => ['number' => 100, 'roman' => 'C'],
            '400'  => ['number' => 400, 'roman' => 'CD'],
            '500'  => ['number' => 500, 'roman' => 'D'],
            '900'  => ['number' => 900, 'roman' => 'CM'],
            '1000' => ['number' => 1000, 'roman' => 'M'],
            '1926' => ['number' => 1926, 'roman' => 'MCMXXVI'],
            '2024' => ['number' => 2024, 'roman' => 'MMXXIV'],
            '3999' => ['number' => 3999, 'roman' => 'MMMCMXCIX'],
        ];
    }

    #[DataProvider('romanNumeralData')]
    public function test_number_to_roman_numerals(int $number, string $roman): void
    {
        $this->assertSame($roman, $this->service->numberToRomanNumerals($number));
    }

    #[DataProvider('romanNumeralData')]
    public function test_roman_numerals_to_number(int $number, string $roman): void
    {
        $this->assertSame($number, $this->service->romanNumeralsToNumber($roman));
    }

    public function test_roman_numerals_to_number_with_upper_case(): void
    {
        $this->assertSame(4, $this->service->romanNumeralsToNumber('IV'));
        $this->assertSame(14, $this->service->romanNumeralsToNumber('XIV'));
    }

    public function test_zero_returns_zero_string(): void
    {
        $this->assertSame('0', $this->service->numberToRomanNumerals(0));
    }

    /**
     * Negative Zahlen werden unverändert als String zurückgegeben (keine römische Notation für negative Werte).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/RomanNumeralsServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_number_to_roman_numerals_returns_string_for_negative_number(): void
    {
        // Arrange
        $negative = -1;

        // Act
        $result = $this->service->numberToRomanNumerals($negative);

        // Assert
        self::assertSame('-1', $result);
    }

    /**
     * Leere Eingabe wird als 0 interpretiert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/RomanNumeralsServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_roman_numerals_to_number_returns_zero_for_empty_string(): void
    {
        // Arrange
        $empty = '';

        // Act
        $result = $this->service->romanNumeralsToNumber($empty);

        // Assert
        self::assertSame(0, $result);
    }
}
