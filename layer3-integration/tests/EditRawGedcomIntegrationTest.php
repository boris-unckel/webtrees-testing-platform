<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactPage;
use Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordPage;

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
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordPage
 * @see docs/testquality_improve_E03.md
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
}
