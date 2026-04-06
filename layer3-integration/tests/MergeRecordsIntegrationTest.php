<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsAction;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsPage;

/**
 * Komponentenintegrationstest: Datensatz-Zusammenführung — P41.
 *
 * Tests:
 * - MergeRecordsPage GET mit zwei gültigen XREFs → 200
 * - MergeRecordsPage GET mit leeren XREFs → 200 (null-Records in View)
 * - MergeRecordsAction POST: zwei INDIs → 302 zu MergeFactsPage
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeRecordsAction
 * @see docs/testquality_improve_P41.md
 */
class MergeRecordsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p41-merge', 'P41 Merge', self::DEMO_GED);
    }

    /**
     * EP1: MergeRecordsPage GET mit zwei gültigen INDI-XREFs → 200.
     */
    public function test_merge_records_page_returns_200_with_valid_xrefs(): void
    {
        $handler = new MergeRecordsPage();

        $request = $this->createRequest(
            query: [
                'xref1' => 'X1030',
                'xref2' => 'X1031',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: MergeRecordsPage GET mit leeren XREFs → 200 (null-Records, kein Fehler).
     * isXref() mit Default '' → make('', $tree) gibt null zurück → null-Felder in View.
     */
    public function test_merge_records_page_returns_200_with_empty_xrefs(): void
    {
        $handler = new MergeRecordsPage();

        $request = $this->createRequest(
            query: [],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: MergeRecordsAction POST mit zwei gültigen INDIs → 302 (zu MergeFactsPage).
     * Beide Records existieren, gleicher Typ → redirect zu MergeFactsPage.
     */
    public function test_merge_records_action_redirects_for_matching_records(): void
    {
        $handler = new MergeRecordsAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'xref1' => 'X1030',
                'xref2' => 'X1031',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
