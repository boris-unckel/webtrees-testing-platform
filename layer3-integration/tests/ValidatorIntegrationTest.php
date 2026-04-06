<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Http\Exceptions\HttpBadRequestException;
use Fisharebest\Webtrees\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstests: Validator (root-Paket).
 *
 * Ergänzt den upstream ValidatorTest (Layer 2) um Layer-3-spezifische Lücken:
 * - float(): vollständige EP/BVA-Matrix (CRAP=12, 0% Layer-3-Coverage)
 * - __construct(): UTF-8-Validierung Key + Value, ASCII-Modus (serverParams)
 * - integer(): negativer String-Pfad (Layer-3-Lücke lines 327–328)
 * - array(): Guard throw für Non-Array-Non-Null (Layer-3-Lücke line 279)
 *
 * Kein Tree/DB-Zugriff notwendig — Bootstrap (I18N, Gedcom-Tags) reicht.
 *
 * @see docs/plan_U01_validator.md
 */
#[CoversClass(Validator::class)]
class ValidatorIntegrationTest extends MysqlTestCase
{
    // -------------------------------------------------------------------------
    // float() — EP/BVA-Matrix (U01 EP1–EP5, EP-inv1/2, EP-miss1/2)
    // -------------------------------------------------------------------------

    /**
     * EP1: Gültiger Float-String liefert Float-Wert.
     */
    public function test_float_returns_float_from_valid_float_string(): void
    {
        $request = $this->createRequest(query: ['param' => '3.14']);

        $result = Validator::queryParams($request)->float('param');

        self::assertSame(3.14, $result);
    }

    /**
     * EP2: Integer-String wird zu Float konvertiert.
     */
    public function test_float_returns_float_from_integer_string(): void
    {
        $request = $this->createRequest(query: ['param' => '42']);

        $result = Validator::queryParams($request)->float('param');

        self::assertSame(42.0, $result);
    }

    /**
     * EP5/BV1: Null-Grenzwert — '0' liefert 0.0.
     */
    public function test_float_returns_zero_from_zero_string(): void
    {
        $request = $this->createRequest(query: ['param' => '0']);

        $result = Validator::queryParams($request)->float('param');

        self::assertSame(0.0, $result);
    }

    /**
     * EP4/BV2: Negativer Float-String liefert negativen Float.
     */
    public function test_float_returns_float_from_negative_string(): void
    {
        $request = $this->createRequest(query: ['param' => '-1.5']);

        $result = Validator::queryParams($request)->float('param');

        self::assertSame(-1.5, $result);
    }

    /**
     * EP3: Int-Typ als Request-Attribut wird zu Float konvertiert.
     *
     * Attribute können beliebige PHP-Typen sein — der Validator nutzt is_numeric().
     */
    public function test_float_returns_float_from_int_typed_attribute(): void
    {
        $request = $this->createRequest(attributes: ['param' => 42]);

        $result = Validator::attributes($request)->float('param');

        self::assertSame(42.0, $result);
    }

    /**
     * EP-inv1: Nicht-numerischer String ohne Default wirft HttpBadRequestException.
     */
    public function test_float_throws_for_non_numeric_string_without_default(): void
    {
        $request = $this->createRequest(query: ['param' => 'abc']);

        $this->expectException(HttpBadRequestException::class);

        Validator::queryParams($request)->float('param');
    }

    /**
     * EP-inv2: Nicht-numerischer String mit Default liefert Default.
     */
    public function test_float_returns_default_for_non_numeric_string(): void
    {
        $request = $this->createRequest(query: ['param' => 'abc']);

        $result = Validator::queryParams($request)->float('param', 99.9);

        self::assertSame(99.9, $result);
    }

    /**
     * EP-miss1: Fehlender Parameter ohne Default wirft HttpBadRequestException.
     */
    public function test_float_throws_for_missing_parameter_without_default(): void
    {
        $request = $this->createRequest();

        $this->expectException(HttpBadRequestException::class);

        Validator::queryParams($request)->float('missing');
    }

    /**
     * EP-miss2: Fehlender Parameter mit Default liefert Default.
     */
    public function test_float_returns_default_for_missing_parameter(): void
    {
        $request = $this->createRequest();

        $result = Validator::queryParams($request)->float('missing', 0.0);

        self::assertSame(0.0, $result);
    }

    // -------------------------------------------------------------------------
    // __construct() — UTF-8-Validierung (lines 64, 67) + ASCII-Modus (line 60)
    // -------------------------------------------------------------------------

    /**
     * Ungültiger UTF-8-Zeichensatz im Parameter-Key wirft HttpBadRequestException.
     *
     * Betrifft __construct line 64: preg_match('//u', $key) !== 1.
     */
    public function test_query_params_throws_for_invalid_utf8_in_key(): void
    {
        $request = $this->createRequest()->withQueryParams(["\xFF" => 'value']);

        $this->expectException(HttpBadRequestException::class);

        Validator::queryParams($request);
    }

    /**
     * Ungültiger UTF-8-Zeichensatz im Parameter-Value wirft HttpBadRequestException.
     *
     * Betrifft __construct line 67: preg_match('//u', $value) !== 1.
     */
    public function test_query_params_throws_for_invalid_utf8_in_value(): void
    {
        $request = $this->createRequest()->withQueryParams(['key' => "\xFF"]);

        $this->expectException(HttpBadRequestException::class);

        Validator::queryParams($request);
    }

    /**
     * serverParams verwendet ASCII-Encoding — non-UTF-8-Inhalte werfen keine Exception.
     *
     * Kommentar im SUT: "some servers add GEOIP headers with non-ASCII placenames."
     * Der if ($encoding === 'UTF-8')-Block wird für serverParams übersprungen.
     * Stub statt withServerParams(), da Nyholm\Psr7 diese Methode nicht exponiert.
     */
    public function test_server_params_allows_non_utf8_content(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['GEOIP_CITY' => "S\xE3o Paulo"]);

        // Kein Werfen einer Exception erwartet
        $validator = Validator::serverParams($request);

        self::assertSame("S\xE3o Paulo", $validator->string('GEOIP_CITY', ''));
    }

    // -------------------------------------------------------------------------
    // integer() — Layer-3-Lücken (lines 327–328, 341)
    // -------------------------------------------------------------------------

    /**
     * Negativer Integer-String wird korrekt zu negativem Int konvertiert.
     *
     * Betrifft __construct lines 327–328: str_starts_with('-') && ctype_digit(substr()).
     * In Layer 2 bereits abgedeckt — Layer-3-Lücke schließen.
     */
    public function test_integer_returns_negative_int_from_negative_string(): void
    {
        $request = $this->createRequest(query: ['param' => '-42']);

        $result = Validator::queryParams($request)->integer('param');

        self::assertSame(-42, $result);
    }

    /**
     * Nicht-numerischer String ohne Default wirft HttpBadRequestException.
     *
     * Betrifft integer() line 341: throw HttpBadRequestException.
     */
    public function test_integer_throws_for_non_numeric_string_without_default(): void
    {
        $request = $this->createRequest(query: ['param' => 'not-an-int']);

        $this->expectException(HttpBadRequestException::class);

        Validator::queryParams($request)->integer('param');
    }

    // -------------------------------------------------------------------------
    // array() — Layer-3-Lücke (line 279)
    // -------------------------------------------------------------------------

    /**
     * Non-Array, non-null Wert für array()-Parameter wirft HttpBadRequestException.
     *
     * Betrifft array() line 279: !is_array($value) && $value !== null → throw.
     */
    public function test_array_throws_for_non_array_non_null_value(): void
    {
        $request = $this->createRequest(query: ['param' => 'not-an-array']);

        $this->expectException(HttpBadRequestException::class);

        Validator::queryParams($request)->array('param');
    }
}
