<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
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
     * Zwei Individuals mergen — Happy-Path-Redirect zu ManageTrees
     * und pending-Löschung von xref2 in der change-Tabelle (EP1, DB-Postcondition).
     */
    public function test_merge_individuals_redirects(): void
    {
        // Zwei INDIs aus demo.ged holen — Fixture-Invariante: demo.ged liefert >= 72 Individuals.
        $xrefs = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->orderBy('i_id')
            ->limit(2)
            ->pluck('i_id')
            ->toArray();

        self::assertGreaterThanOrEqual(2, count($xrefs), 'demo.ged-Fixture muss mindestens zwei Individuals enthalten.');

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

        // Happy-Path-Redirect: 302 zu ManageTrees — Location enthält kein 'xref1' (Guard-Redirect-Signatur).
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringNotContainsString('xref1', $response->getHeaderLine('Location'));

        // DB-Postcondition: deleteRecord() legt für xref2 einen change-Eintrag mit new_gedcom='' an.
        $deleted = DB::table('change')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('xref', '=', $xref2)
            ->where('new_gedcom', '=', '')
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();
        self::assertTrue($deleted, 'Für xref2 muss eine pending/accepted Löschung in der change-Tabelle stehen.');
    }
}
