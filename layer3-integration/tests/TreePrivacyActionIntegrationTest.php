<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\Exceptions\HttpBadRequestException;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyAction;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyPage;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstest: TreePrivacyAction HTTP-Handler.
 *
 * EP-Matrix: Mismatched-Arrays → HttpBadRequestException (EP3/EP4),
 * Rule-Typ-Matrix: tag+xref (EP5), tag-only (EP6), xref-only (EP7),
 * beide-leer kein Insert (EP8), Privacy-Setting gespeichert (EP9).
 * Assertion: default_resn-Tabelle + tree->getPreference().
 *
 * @see docs/tds_conditions_ref.md P33
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\TreePrivacyPage
 */
class TreePrivacyActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';
    private const TEST_XREF = 'X1030';
    private const TEST_TAG  = 'BIRT';
    private const TEST_RESN = 'privacy';

    private TreePrivacyAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('p33-demo', 'Demo', self::DEMO_GED);
        $this->handler = new TreePrivacyAction();
    }

    /**
     * Standard-POST-Request mit optionalen Überschreibungen.
     * Alle 8 Privacy-Pflicht-Parameter sind immer enthalten.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeRequest(array $overrides = []): ServerRequestInterface
    {
        return $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     array_merge([
                'delete'                     => [],
                'xref'                       => [],
                'tag_type'                   => [],
                'resn'                       => [],
                'HIDE_LIVE_PEOPLE'           => '1',
                'KEEP_ALIVE_YEARS_BIRTH'     => '0',
                'KEEP_ALIVE_YEARS_DEATH'     => '0',
                'MAX_ALIVE_AGE'              => '100',
                'REQUIRE_AUTHENTICATION'     => '0',
                'SHOW_DEAD_PEOPLE'           => '1',
                'SHOW_LIVING_NAMES'          => '0',
                'SHOW_PRIVATE_RELATIONSHIPS' => '1',
            ], $overrides),
            attributes: ['tree' => $this->tree],
        );
    }

    /**
     * xrefs.count ≠ tag_types.count → HttpBadRequestException (EP3).
     */
    public function test_tree_privacy_throws_on_mismatched_array_lengths(): void
    {
        $this->expectException(HttpBadRequestException::class);

        $this->handler->handle($this->makeRequest([
            'xref'     => [self::TEST_XREF, 'X1031'],
            'tag_type' => [self::TEST_TAG],
            'resn'     => [self::TEST_RESN],
        ]));
    }

    /**
     * tag+xref beide gefüllt → Regel in default_resn eingefügt (B2+B5/EP5).
     * xref='X1030', tag='BIRT' → Zeile mit beiden Werten gesetzt.
     */
    public function test_tree_privacy_inserts_tag_xref_rule(): void
    {
        $response = $this->handler->handle($this->makeRequest([
            'xref'     => [self::TEST_XREF],
            'tag_type' => [self::TEST_TAG],
            'resn'     => [self::TEST_RESN],
        ]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertTrue(DB::table('default_resn')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('xref', '=', self::TEST_XREF)
            ->where('tag_type', '=', self::TEST_TAG)
            ->where('resn', '=', self::TEST_RESN)
            ->exists());
    }

    /**
     * tag gesetzt, xref leer → Nur-Tag-Regel eingefügt, xref=NULL (B3+B5/EP6).
     */
    public function test_tree_privacy_inserts_tag_only_rule(): void
    {
        $response = $this->handler->handle($this->makeRequest([
            'xref'     => [''],
            'tag_type' => [self::TEST_TAG],
            'resn'     => [self::TEST_RESN],
        ]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertTrue(DB::table('default_resn')
            ->where('gedcom_id', '=', $this->tree->id())
            ->whereNull('xref')
            ->where('tag_type', '=', self::TEST_TAG)
            ->where('resn', '=', self::TEST_RESN)
            ->exists());
    }

    /**
     * xref gesetzt, tag leer → Nur-XREF-Regel eingefügt, tag_type=NULL (B4+B5/EP7).
     */
    public function test_tree_privacy_inserts_xref_only_rule(): void
    {
        $response = $this->handler->handle($this->makeRequest([
            'xref'     => [self::TEST_XREF],
            'tag_type' => [''],
            'resn'     => [self::TEST_RESN],
        ]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertTrue(DB::table('default_resn')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('xref', '=', self::TEST_XREF)
            ->whereNull('tag_type')
            ->where('resn', '=', self::TEST_RESN)
            ->exists());
    }

    /**
     * tag+xref beide leer → kein Insert in default_resn (B5 false/EP8).
     * TreeService::create() kopiert default_resn-Einträge von gedcom_id=-1 → count vor/nach gleich.
     */
    public function test_tree_privacy_does_not_insert_when_both_empty(): void
    {
        $countBefore = DB::table('default_resn')
            ->where('gedcom_id', '=', $this->tree->id())
            ->count();

        $response = $this->handler->handle($this->makeRequest([
            'xref'     => [''],
            'tag_type' => [''],
            'resn'     => [self::TEST_RESN],
        ]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $countAfter = DB::table('default_resn')
            ->where('gedcom_id', '=', $this->tree->id())
            ->count();
        $this->assertSame($countBefore, $countAfter);
    }

    /**
     * HIDE_LIVE_PEOPLE=1 → tree-Preference gesetzt (EP9, DB-Postcondition).
     * SUT operiert auf $this->tree (gleicher Objekt-Verweis via request-Attribute).
     */
    public function test_tree_privacy_saves_hide_live_people_setting(): void
    {
        $response = $this->handler->handle($this->makeRequest(['HIDE_LIVE_PEOPLE' => '1']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        $this->assertSame('1', $this->tree->getPreference('HIDE_LIVE_PEOPLE'));
    }

    /**
     * TreePrivacyPage GET-Handler → 200 OK mit gerendertem Privacy-Admin-View.
     * Validiert, dass der Page-Handler Tree-Attribut korrekt extrahiert und die
     * Privacy-Konfigurationsseite ohne Fehler ausliefert.
     *
     * @group ported-l2-doubles
     */
    public function test_tree_privacy_page_returns_ok_response(): void
    {
        $page_handler = new TreePrivacyPage($this->treeService);
        $request      = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $page_handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
