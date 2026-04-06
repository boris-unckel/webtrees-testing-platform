<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectRecord;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\PendingChangesService;

/**
 * Komponentenintegrationstest: PendingChanges-Aktionen — P40.
 *
 * Tests:
 * - AcceptRecord mit ungültiger XREF → response() 200 (kein acceptRecord-Aufruf)
 * - RejectRecord mit ungültiger XREF → response() 200
 * - PendingChanges GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesAcceptRecord
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesRejectRecord
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges
 * @see docs/testquality_improve_P40.md
 */
class PendingChangesIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p40-pending', 'P40 Pending', self::DEMO_GED);
    }

    /**
     * EP1: AcceptRecord — ungültige XREF → kein acceptRecord-Aufruf → response() 200.
     * Validator::isXref() lässt 'DOESNOTEXIST' durch; make() gibt null zurück.
     */
    public function test_accept_record_unknown_xref_returns_200(): void
    {
        $handler = new PendingChangesAcceptRecord(
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'DOESNOTEXIST',
            ],
        );

        $response = $handler->handle($request);

        // response() in webtrees returns HTTP 204 (No Content)
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP3: RejectRecord — ungültige XREF → kein rejectRecord-Aufruf → response() 204.
     */
    public function test_reject_record_unknown_xref_returns_204(): void
    {
        $handler = new PendingChangesRejectRecord(
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'DOESNOTEXIST',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP5: PendingChanges GET → 200.
     */
    public function test_pending_changes_page_returns_200(): void
    {
        $handler = new PendingChanges(
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            query: ['url' => 'https://webtrees.test/'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
