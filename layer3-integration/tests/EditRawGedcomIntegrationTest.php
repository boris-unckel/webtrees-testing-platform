<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactPage;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordPage;
use Fisharebest\Webtrees\Registry;

/**
 * Komponentenintegrationstest: Rohdaten-Edit (Raw GEDCOM) — E03.
 *
 * Tests:
 * - EditRawFactPage GET: ungültige fact_id → redirect
 * - EditRawRecordPage GET: gültige XREF → 200
 * - EditRawFactAction POST: ungültige fact_id → redirect (kein Update, kein Fehler)
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordPage
 * @see docs/testquality_improve_E03.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawFactPageTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawRecordActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawRecordPageTest.php
 */
class EditRawGedcomIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e03-rawgedcom', 'E03 RawGedcom', self::DEMO_GED);
    }

    /**
     * EP1: EditRawFactPage GET — ungültige fact_id → redirect zu Record-URL.
     */
    public function test_edit_raw_fact_page_redirects_for_unknown_fact_id(): void
    {
        $handler = new EditRawFactPage();

        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => 'NONEXISTENT_FACT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP4: EditRawRecordPage GET: gültige XREF → 200, View mit GEDCOM.
     */
    public function test_edit_raw_record_page_returns_200(): void
    {
        $handler = new EditRawRecordPage();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: EditRawFactAction POST — ungültige fact_id → Schleife findet keinen Treffer → redirect.
     */
    public function test_edit_raw_fact_action_redirects_for_unknown_fact_id(): void
    {
        $handler = new EditRawFactAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => 'NONEXISTENT_FACT',
            ],
            params: [
                'gedcom' => '1 BIRT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EditRawFactAction POST — unbekannte XREF: Auth::checkRecordAccess(null) wirft HttpNotFoundException,
     * bevor die Fakten-Schleife durchlaufen wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawFactActionTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_raw_fact_action_throws_not_found_for_missing_record(): void
    {
        // Arrange
        $handler = new EditRawFactAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X_NONEXISTENT',
                'fact_id' => 'test_fact',
            ],
            params: [
                'gedcom' => '1 NAME Test /Name/',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EditRawFactPage GET happy path: gültige XREF + gültige fact_id eines vorhandenen
     * Facts → Handler liefert 200 mit gerenderter Raw-Edit-Maske.
     *
     * Der Quell-Stub-Test simuliert Fact und GedcomRecord über self::createStub() und
     * ein gemocktes GedcomRecordFactoryInterface. Hier wird der happy path real über
     * einen aus dem importierten demo.ged stammenden Record (X1030) abgebildet — die
     * erste vorhandene Fact-ID wird zur Laufzeit ermittelt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawFactPageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_raw_fact_page_returns_200_for_valid_fact_id(): void
    {
        // Arrange — echten Record laden, Fact-ID zur Laufzeit ermitteln
        $record = Registry::gedcomRecordFactory()->make('X1030', $this->tree);
        self::assertNotNull($record, 'Demo-GEDCOM muss X1030 enthalten');

        /** @var Fact|null $first_fact */
        $first_fact = $record->facts()->first();
        self::assertNotNull($first_fact, 'X1030 muss mindestens einen Fact haben');

        $handler = new EditRawFactPage();

        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => $first_fact->id(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EditRawFactPage GET mit XREF-Form, aber im Baum nicht vorhandener XREF →
     * GedcomRecordFactory::make() liefert null → Auth::checkRecordAccess(null, true)
     * wirft HttpNotFoundException. Der Quell-Stub-Test ersetzt die Factory per
     * Registry-Override; hier wird stattdessen der echte „Record fehlt"-Pfad
     * gegen MySQL geprüft.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawFactPageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_raw_fact_page_throws_not_found_for_unknown_record(): void
    {
        // Arrange
        $handler = new EditRawFactPage();

        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X999',
                'fact_id' => 'fact-123',
            ],
        );

        // Assert (Exception-Erwartung vor Act)
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * EditRawRecordAction POST — unbekannte XREF: Auth::checkRecordAccess(null) wirft
     * HttpNotFoundException, bevor die Fakten-Schleife durchlaufen wird.
     *
     * Der Quell-Stub-Test ersetzt die GedcomRecordFactory über Registry-Override und
     * lässt sie für einen unbekannten XREF null zurückgeben. Hier wird stattdessen
     * der reale „Record fehlt"-Pfad gegen MySQL geprüft — die Factory liefert null,
     * Auth::checkRecordAccess(null, true) wirft die Exception.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawRecordActionTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_raw_record_action_throws_not_found_for_missing_record(): void
    {
        // Arrange
        $handler = new EditRawRecordAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
            params: [
                'level0'  => '',
                'fact'    => [],
                'fact_id' => [],
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EditRawRecordPage GET mit im Baum nicht vorhandener XREF →
     * GedcomRecordFactory::make() liefert null → Auth::checkRecordAccess(null, true)
     * wirft HttpNotFoundException. Der Quell-Stub-Test ersetzt die Factory per
     * Registry-Override; hier wird stattdessen der echte „Record fehlt"-Pfad
     * gegen MySQL geprüft.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditRawRecordPageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_raw_record_page_throws_not_found_for_unknown_record(): void
    {
        // Arrange
        $handler = new EditRawRecordPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Assert (Exception-Erwartung vor Act)
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }
}
