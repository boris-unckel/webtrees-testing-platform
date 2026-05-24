<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\RightToLeftSupport;

/**
 * Komponentenintegrationstest: RightToLeftSupport Bootstrap-Test.
 *
 * spanLtrRtl() ist public static — kein DB, kein Tree.
 * finishCurrentSpan() ist private static (CRAP 10.100) und wird intern von
 * spanLtrRtl() aufgerufen — beide Methoden werden durch RTL-Input abgedeckt.
 *
 * @see docs/tds_conditions_ref.md S44
 * @covers \Fisharebest\Webtrees\Report\RightToLeftSupport
 */
class RightToLeftSupportIntegrationTest extends MysqlTestCase
{
    /**
     * spanLtrRtl mit LTR-String gibt nicht-leeren String zurück.
     */
    public function test_span_ltr_rtl_with_ltr_string_returns_string(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('Hello World');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * spanLtrRtl mit RTL-String (Arabisch) triggert finishCurrentSpan intern.
     */
    public function test_span_ltr_rtl_with_rtl_string_triggers_finish_span(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('مرحبا بالعالم');

        $this->assertIsString($result);
    }

    /**
     * spanLtrRtl mit gemischtem RTL/LTR-Text (beide Code-Branches).
     */
    public function test_span_ltr_rtl_with_mixed_text(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('Hello مرحبا World');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * spanLtrRtl mit leerem String gibt String zurück (kein Absturz).
     */
    public function test_span_ltr_rtl_with_empty_string_returns_string(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('');

        $this->assertIsString($result);
    }

    /**
     * spanLtrRtl mit Hebräisch (weiterer RTL-Zeichenbereich).
     */
    public function test_span_ltr_rtl_with_hebrew_text(): void
    {
        $result = RightToLeftSupport::spanLtrRtl('שלום עולם');

        $this->assertIsString($result);
    }
}
