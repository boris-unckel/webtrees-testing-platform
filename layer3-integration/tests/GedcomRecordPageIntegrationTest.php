<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: GedcomRecordPage HTTP-Handler.
 *
 * EP-Matrix: Standard-Record-Typen (INDI/FAM/SOUR/REPO) → Redirect 3xx (EP1);
 * nicht-standardisierter Record-Typ (_CUST) → 200 record-page mit Link-Header (EP2).
 * Befund aus P1: Smoke-Tests prüften nur < 400; tatsächliches Verhalten ist 302-Redirect.
 *
 * @see docs/tds_conditions_ref.md P32
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage
 */
class GedcomRecordPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private GedcomRecordPage $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p32-page', 'P32 Page', self::DEMO_GED);
        $this->handler = new GedcomRecordPage(new ClipboardService(), new LinkedRecordService());
    }

    /**
     * DataProvider: Standard-Record-Typen aus demo.ged.
     *
     * @return array<string, array{string}>
     */
    public static function standardRecordXrefs(): array
    {
        return [
            'individual' => ['X1030'],
            'family'     => ['f1'],
            'source'     => ['X1102'],
            'repository' => ['X1165'],
        ];
    }

    /**
     * Alle Standard-Record-Typen werden auf ihre spezifische Seite weitergeleitet (EP1).
     * GedcomRecordPage leitet INDI, FAM, SOUR, REPO (und weitere STANDARD_RECORDS) mit 302 weiter —
     * die Smoke-Tests prüften nur status < 400 und verdeckten dieses Verhalten.
     */
    #[DataProvider('standardRecordXrefs')]
    public function test_standard_record_types_redirect_to_specific_page(string $xref): void
    {
        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => $xref]);
        $response = $this->handler->handle($request);

        $this->assertGreaterThanOrEqual(300, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    /**
     * Nicht-standardisierter Record-Typ (_CUST) rendert record-page mit 200 + Link-Header (EP2).
     * Nicht-Standard-Typen landen nicht in STANDARD_RECORDS → handler gibt viewResponse zurück.
     * Der Link-Header enthält die kanonische URL des Records.
     */
    public function test_non_standard_record_renders_record_page_with_link_header(): void
    {
        $xref = 'C1TEST';
        DB::table('other')->insert([
            'o_id'     => $xref,
            'o_file'   => $this->tree->id(),
            'o_type'   => '_CUST',
            'o_gedcom' => "0 @{$xref}@ _CUST\n1 NOTE Custom test record for P32",
        ]);

        $request  = $this->createRequest(attributes: ['tree' => $this->tree, 'xref' => $xref]);
        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Link'));
        $this->assertStringContainsString($xref, $response->getHeaderLine('Link'));
    }
}
