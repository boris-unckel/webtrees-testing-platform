<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Report\RightToLeftFormatter;

/**
 * Komponentenintegrationstest: RightToLeftFormatter Bootstrap-Test.
 *
 * format() ist eine public Instanzmethode (kein DB, kein Tree). Jede Instanz
 * verarbeitet genau einen Eingabe-String; die interne Span-Logik wird durch
 * RTL-Input abgedeckt.
 *
 * Upstream-Umbau (PR #5389, Commit aaced7b07e): die ehemals statische
 * RightToLeftSupport::spanLtrRtl() wurde zur re-entranten Instanzmethode
 * RightToLeftFormatter::format() refaktoriert.
 *
 * @see docs/tds_conditions_ref.md S44
 * @covers \Fisharebest\Webtrees\Report\RightToLeftFormatter
 */
class RightToLeftSupportIntegrationTest extends MysqlTestCase
{
    /**
     * format() mit LTR-String gibt nicht-leeren String zurück.
     */
    public function test_span_ltr_rtl_with_ltr_string_returns_string(): void
    {
        $result = (new RightToLeftFormatter())->format('Hello World');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * format() mit RTL-String (Arabisch) triggert finishCurrentSpan intern.
     */
    public function test_span_ltr_rtl_with_rtl_string_triggers_finish_span(): void
    {
        $result = (new RightToLeftFormatter())->format('مرحبا بالعالم');

        $this->assertIsString($result);
    }

    /**
     * format() mit gemischtem RTL/LTR-Text (beide Code-Branches).
     */
    public function test_span_ltr_rtl_with_mixed_text(): void
    {
        $result = (new RightToLeftFormatter())->format('Hello مرحبا World');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * format() mit leerem String gibt String zurück (kein Absturz).
     */
    public function test_span_ltr_rtl_with_empty_string_returns_string(): void
    {
        $result = (new RightToLeftFormatter())->format('');

        $this->assertIsString($result);
    }

    /**
     * format() mit Hebräisch (weiterer RTL-Zeichenbereich).
     */
    public function test_span_ltr_rtl_with_hebrew_text(): void
    {
        $result = (new RightToLeftFormatter())->format('שלום עולם');

        $this->assertIsString($result);
    }
}
