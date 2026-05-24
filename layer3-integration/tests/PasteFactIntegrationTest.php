<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\GedcomRecordFactoryInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\PasteFact;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;

/**
 * Komponentenintegrationstest: PasteFact HTTP-Handler.
 *
 * Verifiziert das Einfügen einer Tatsache (Fact) aus dem Clipboard-Service in
 * einen Ziel-Record:
 * - GedcomRecordFactory liefert einen Edit-Record → ClipboardService::pasteFact()
 *   wird genau einmal mit der fact_id und dem Record aufgerufen, der Handler
 *   antwortet mit 302-Redirect auf die Record-URL.
 *
 * Stub/Mock-Konvention: Domain-Objekt (GedcomRecord) als Stub mit canEdit/canShow,
 * GedcomRecordFactoryInterface ebenfalls als Stub, ClipboardService als Mock mit
 * `expects(once())`-Verifikation des pasteFact-Aufrufs.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PasteFact
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasteFactTest.php
 */
class PasteFactIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('paste-fact', 'PasteFact Test', self::DEMO_GED);
    }

    /**
     * Der 302-Redirect propagiert die vom GedcomRecord gelieferte URL
     * unverändert in den Location-Header.
     *
     * Pinnt die `redirect($record->url())`-Propagation: Der Location-Header
     * muss exakt dem `$record->url()`-Rückgabewert entsprechen. Diese Property
     * ist von test_handle_pastes_fact_and_redirects nicht abgedeckt — dort wird
     * nur der Statuscode geprüft. Ersetzt eine class_exists-Tautologie aus dem
     * L2-Port (BEHAVIOR_HANDLE / L3SP-044).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasteFactTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirect_location_propagates_record_url(): void
    {
        // Arrange
        $this->createAndLoginAdmin();

        $expected_url = 'https://webtrees.test/record/X2-url-propagation';

        $record = self::createStub(GedcomRecord::class);
        $record->method('xref')->willReturn('X2');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('url')->willReturn($expected_url);

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);
        Registry::gedcomRecordFactory($record_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service->expects(self::once())
            ->method('pasteFact')
            ->with('fact-url-prop', $record);

        $handler = new PasteFact($clipboard_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     ['fact_id' => 'fact-url-prop'],
            attributes: ['tree' => $this->tree, 'xref' => 'X2'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Location-Header trägt exakt die vom Record gelieferte URL.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame($expected_url, $response->getHeaderLine('location'));
    }

    /**
     * PasteFact ruft ClipboardService::pasteFact() genau einmal mit der
     * übergebenen fact_id und dem Edit-Record auf und leitet anschließend
     * mit 302 auf die Record-URL weiter.
     *
     * Die GedcomRecordFactory wird per Stub ausgetauscht, damit der Test
     * unabhängig von einer realen GEDCOM-XREF läuft. canEdit/canShow sind
     * gesetzt, damit Auth::checkRecordAccess(..., true) den Edit-Pfad
     * akzeptiert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/PasteFactTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_pastes_fact_and_redirects(): void
    {
        // Arrange
        $this->createAndLoginAdmin();

        $record = self::createStub(GedcomRecord::class);
        $record->method('xref')->willReturn('X1');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('url')->willReturn('https://webtrees.test/record/X1');

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);
        Registry::gedcomRecordFactory($record_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service->expects(self::once())
            ->method('pasteFact')
            ->with('some-fact-id', $record);

        $handler = new PasteFact($clipboard_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['fact_id' => 'some-fact-id'],
            attributes: ['tree' => $this->tree, 'xref' => 'X1'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
