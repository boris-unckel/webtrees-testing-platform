<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\ChangeFamilyMembersAction;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Komponentenintegrationstest: ChangeFamilyMembersAction HTTP-Handler.
 *
 * EP-Matrix: Vater-Austausch (B1+B5/EP1), Mutter-Entfernung (B2/EP2),
 * Kind-Hinzufügen (B4/EP3), Kind-Entfernen (B3/EP4), keine Änderung (EP5).
 * Assertion: change-Tabelle (pending-Einträge für betroffene Records).
 * B7/B8 (Datumsreihenfolge-Sortierung) ausgeklammert — Pragmatisch C, komplex.
 *
 * @see docs/tds_conditions_ref.md P31
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ChangeFamilyMembersAction
 */
class ChangeFamilyMembersActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    // Family f1 in demo.ged: HUSB=X1041, WIFE=X1030, CHIL=[X1052, X1063, X1074, X1085]
    private const FAM        = 'f1';
    private const HUSB       = 'X1041';
    private const WIFE       = 'X1030';
    private const CHIL_ALL   = ['X1052', 'X1063', 'X1074', 'X1085'];
    // X1031 — individual with no FAMS (no marriage), only FAMC @f29@; used as new husband or extra child
    private const NEW_MEMBER = 'X1031';
    private const FIRST_CHIL = 'X1052'; // first child — used for remove-child test

    private ChangeFamilyMembersAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('p31-demo', 'Demo', self::DEMO_GED);
        $this->handler = new ChangeFamilyMembersAction();
    }

    /**
     * Standard-POST-Request auf f1 mit optionalen Überschreibungen.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeRequest(array $overrides = []): ServerRequestInterface
    {
        return $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     array_merge([
                'xref' => self::FAM,
                'HUSB' => self::HUSB,
                'WIFE' => self::WIFE,
                'CHIL' => self::CHIL_ALL,
            ], $overrides),
            attributes: ['tree' => $this->tree],
        );
    }

    /**
     * Hilfsmethode: change-Eintrag für xref + tree vorhanden?
     */
    private function hasChangeFor(string $xref): bool
    {
        return DB::table('change')
            ->where('gedcom_id', '=', $this->tree->id())
            ->where('xref', '=', $xref)
            ->exists();
    }

    /**
     * Vater austauschen: B1 (altes FAMS entfernen) + B5 (neues FAMS/HUSB erstellen) (EP1).
     * Altes HUSB X1041 → neues HUSB X1031 (kein FAMS, nur FAMC).
     */
    public function test_change_family_replaces_husband(): void
    {
        $response = $this->handler->handle($this->makeRequest(['HUSB' => self::NEW_MEMBER]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Alter Vater X1041: change-Eintrag (FAMS @f1@ entfernt)
        $this->assertTrue($this->hasChangeFor(self::HUSB));
        // Neuer Vater X1031: change-Eintrag (FAMS @f1@ hinzugefügt)
        $this->assertTrue($this->hasChangeFor(self::NEW_MEMBER));
        // Familie f1: change-Einträge (HUSB-Link aktualisiert)
        $this->assertTrue($this->hasChangeFor(self::FAM));
    }

    /**
     * Mutter entfernen: WIFE='' → null → B2 ausgelöst (EP2).
     * Alte WIFE X1030 verliert FAMS @f1@; Familie verliert WIFE-Link.
     */
    public function test_change_family_removes_wife(): void
    {
        $response = $this->handler->handle($this->makeRequest(['WIFE' => '']));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Alte Mutter X1030: change-Eintrag (FAMS @f1@ entfernt)
        $this->assertTrue($this->hasChangeFor(self::WIFE));
        // Familie f1: change-Eintrag (WIFE-Link entfernt)
        $this->assertTrue($this->hasChangeFor(self::FAM));
    }

    /**
     * Neues Kind hinzufügen: B4 ausgelöst (EP3).
     * X1031 (hat FAMC @f29@, kein FAMS) erhält zusätzliches FAMC @f1@.
     */
    public function test_change_family_adds_new_child(): void
    {
        $children = array_merge(self::CHIL_ALL, [self::NEW_MEMBER]);
        $response = $this->handler->handle($this->makeRequest(['CHIL' => $children]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Neues Kind X1031: change-Eintrag (FAMC @f1@ hinzugefügt)
        $this->assertTrue($this->hasChangeFor(self::NEW_MEMBER));
        // Familie f1: change-Eintrag (CHIL @X1031@ hinzugefügt)
        $this->assertTrue($this->hasChangeFor(self::FAM));
    }

    /**
     * Kind entfernen: B3 ausgelöst (EP4).
     * X1052 (erstes Kind von f1) verliert FAMC @f1@; Familie verliert CHIL-Link.
     */
    public function test_change_family_removes_child(): void
    {
        $children = array_values(array_diff(self::CHIL_ALL, [self::FIRST_CHIL]));
        $response = $this->handler->handle($this->makeRequest(['CHIL' => $children]));

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Entferntes Kind X1052: change-Eintrag (FAMC @f1@ entfernt)
        $this->assertTrue($this->hasChangeFor(self::FIRST_CHIL));
        // Familie f1: change-Eintrag (CHIL @X1052@ entfernt)
        $this->assertTrue($this->hasChangeFor(self::FAM));
    }

    /**
     * Keine Änderung: gleiche Mitglieder → kein DB-Schreibzugriff (EP5).
     * Factory-Cache gibt identische Objekte zurück → === Vergleich true → keine Branches aktiv.
     */
    public function test_change_family_no_change_creates_no_pending_changes(): void
    {
        $response = $this->handler->handle($this->makeRequest());

        $this->assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        // Keine Änderungen → keine change-Einträge in dieser Tree-ID
        $count = DB::table('change')
            ->where('gedcom_id', '=', $this->tree->id())
            ->count();
        $this->assertSame(0, $count);
    }
}
