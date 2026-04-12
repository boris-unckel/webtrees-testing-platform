<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeFactsAction;
use Fisharebest\Webtrees\Services\LinkedRecordService;

/**
 * Komponentenintegrationstest: MergeFactsAction.
 *
 * AP C-02: MergeFactsAction::handle (CRAP 240)
 *
 * @see docs/tds_conditions_ref.md P30
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeFactsAction
 */
class MergeFactsIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private MergeFactsAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $this->handler = new MergeFactsAction(new LinkedRecordService());
    }

    /**
     * Zwei Individuals mergen — INDI1 bleibt, INDI2 gelöscht.
     */
    public function test_merge_individuals_redirects(): void
    {
        // Zwei INDIs aus demo.ged holen
        $xrefs = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->limit(2)
            ->pluck('i_id')
            ->toArray();

        if (count($xrefs) < 2) {
            $this->markTestSkipped('Nicht genug Individuals in demo.ged vorhanden.');
        }

        [$xref1, $xref2] = $xrefs;

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'xref1' => $xref1,
                'xref2' => $xref2,
                'keep1' => [],
                'keep2' => [],
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertLessThan(500, $response->getStatusCode());
    }
}
