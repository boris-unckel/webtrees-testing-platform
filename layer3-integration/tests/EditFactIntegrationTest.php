<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\AddNewFact;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteFact;
use Fisharebest\Webtrees\Http\RequestHandlers\EditFactPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;

/**
 * Komponentenintegrationstest: Fakten bearbeiten — E02.
 *
 * Tests:
 * - EditFactPage GET: ungültige fact_id → redirect
 * - DeleteFact POST: ungültige fact_id → 204 (kein Delete)
 * - AddNewFact GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditFactPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteFact
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddNewFact
 * @see docs/testquality_improve_E02.md
 */
class EditFactIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e02-editfact', 'E02 EditFact', self::DEMO_GED);
    }

    /**
     * EP2: EditFactPage GET — fact_id nicht gefunden → redirect zu Record-URL.
     */
    public function test_edit_fact_page_redirects_for_unknown_fact_id(): void
    {
        $handler = new EditFactPage(
            Registry::container()->get(GedcomEditService::class),
        );

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
     * EP5: DeleteFact POST — gültige XREF, ungültige fact_id → kein Delete → response() 204.
     * Die Schleife über record->facts() findet keinen Treffer, Loop-Body wird nicht ausgeführt.
     */
    public function test_delete_fact_returns_204_for_unknown_fact_id(): void
    {
        $handler = new DeleteFact();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => 'NONEXISTENT_FACT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP3: AddNewFact GET → 200.
     */
    public function test_add_new_fact_page_returns_200(): void
    {
        $handler = new AddNewFact(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
                'fact' => 'BIRT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
