<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\MergeFactsAction;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstest: MergeFactsAction HTTP-Handler.
 *
 * EP-Matrix: record-not-found (B1/EP2), same-record (B3/EP4), tag-mismatch (B4/EP5),
 * pending-deletion (B5/EP6), DB-Postcondition nach Merge (EP1).
 * Guard-Redirect → MergeRecordsPage (Location enthält 'xref1').
 * Happy-Path-Redirect → ManageTrees (Location enthält kein 'xref1').
 *
 * @see docs/testing-bigpicture.md P30
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MergeFactsAction
 */
class MergeFactsActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';
    private const INDI1    = 'X1030';
    private const INDI2    = 'X1031';
    private const SOUR1    = 'X1102';

    private MergeFactsAction $handler;
    private UserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('p30-demo', 'Demo', self::DEMO_GED);
        $this->handler = new MergeFactsAction(new LinkedRecordService());
    }

    /**
     * Standard-POST-Request mit optionalen Überschreibungen.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeRequest(array $overrides = []): ServerRequestInterface
    {
        return $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     array_merge([
                'xref1' => self::INDI1,
                'xref2' => self::INDI2,
                'keep1' => [],
                'keep2' => [],
            ], $overrides),
            attributes: ['tree' => $this->tree],
        );
    }

    /**
     * Nicht existierende xref → record1 null → Guard-Redirect zu MergeRecordsPage (B1/EP2).
     */
    public function test_merge_redirects_when_record_not_found(): void
    {
        $response = $this->handler->handle($this->makeRequest(['xref1' => 'X9999']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('xref1', $response->getHeaderLine('Location'));
    }

    /**
     * xref1 === xref2 → gleicher Record → Guard-Redirect zu MergeRecordsPage (B3/EP4).
     */
    public function test_merge_redirects_when_same_record(): void
    {
        $response = $this->handler->handle($this->makeRequest(['xref2' => self::INDI1]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('xref1', $response->getHeaderLine('Location'));
    }

    /**
     * INDI + SOUR → Tag-Mismatch → Guard-Redirect zu MergeRecordsPage (B4/EP5).
     */
    public function test_merge_redirects_when_records_have_different_tags(): void
    {
        $response = $this->handler->handle($this->makeRequest(['xref2' => self::SOUR1]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('xref1', $response->getHeaderLine('Location'));
    }

    /**
     * Record1 pending deletion → Guard-Redirect zu MergeRecordsPage (B5/EP6).
     * Pending deletion via direktem DB-Insert in change-Tabelle simuliert.
     */
    public function test_merge_redirects_when_record_pending_deletion(): void
    {
        DB::table('change')->insert([
            'gedcom_id'  => $this->tree->id(),
            'xref'       => self::INDI1,
            'old_gedcom' => '0 @' . self::INDI1 . '@ INDI',
            'new_gedcom' => '',
            'status'     => 'pending',
            'user_id'    => $this->admin->id(),
        ]);

        $response = $this->handler->handle($this->makeRequest());

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('xref1', $response->getHeaderLine('Location'));
    }

    /**
     * Happy Path: deleteRecord() schreibt pending-Löschung in change-Tabelle (EP1, DB-Postcondition).
     * deleteRecord() schreibt immer in change-Tabelle (new_gedcom=''), unabhängig von auto_accept.
     * Redirect zu ManageTrees — Location enthält kein 'xref1'.
     */
    public function test_merge_creates_pending_deletion_for_record2(): void
    {
        $response = $this->handler->handle($this->makeRequest());

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertStringNotContainsString('xref1', $response->getHeaderLine('Location'));

        // deleteRecord() legt immer einen change-Eintrag mit new_gedcom='' an
        $deleted = DB::table('change')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('xref', '=', self::INDI2)
            ->where('new_gedcom', '=', '')
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();
        $this->assertTrue($deleted);
    }
}
