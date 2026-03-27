<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\DB;

/**
 * Komponentenintegrationstest: Beziehungen in der Datenbank.
 *
 * Prüft, dass Ehe-, Kind- und Eltern-Beziehungen nach dem GEDCOM-Import
 * korrekt in der MySQL-Datenbank abgebildet sind.
 *
 * @covers \Fisharebest\Webtrees\Services\GedcomImportService
 * @see docs/testing-bigpicture-prompt.md G02
 */
class RelationshipDbTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * G02 — Familien haben verknüpfte Ehepartner (HUSB/WIFE)
     */
    public function test_families_have_linked_spouses(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — Familien mit HUSB-Referenz
        $withHusband = DB::table('families')
            ->where('f_file', '=', $this->tree->id())
            ->whereNot('f_husb', '=', '')
            ->count();

        $this->assertGreaterThan(0, $withHusband, 'Es müssen Familien mit Ehemann existieren');

        // Familien mit WIFE-Referenz
        $withWife = DB::table('families')
            ->where('f_file', '=', $this->tree->id())
            ->whereNot('f_wife', '=', '')
            ->count();

        $this->assertGreaterThan(0, $withWife, 'Es müssen Familien mit Ehefrau existieren');
    }

    /**
     * G02 — Link-Tabelle verbindet Individuen mit Familien
     */
    public function test_link_table_connects_individuals_to_families(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — FAMS-Links (Person ist Ehepartner in Familie)
        $spouseLinks = DB::table('link')
            ->where('l_file', '=', $this->tree->id())
            ->where('l_type', '=', 'FAMS')
            ->count();

        $this->assertGreaterThan(0, $spouseLinks, 'FAMS-Links müssen existieren');

        // FAMC-Links (Person ist Kind in Familie)
        $childLinks = DB::table('link')
            ->where('l_file', '=', $this->tree->id())
            ->where('l_type', '=', 'FAMC')
            ->count();

        $this->assertGreaterThan(0, $childLinks, 'FAMC-Links müssen existieren');
    }

    /**
     * G02 — Mehrgenerationen-Beziehungen: Großeltern → Eltern → Kinder
     */
    public function test_multigenerational_relationships_exist(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — Es gibt Personen, die sowohl Kind (FAMC) als auch Elternteil (FAMS) sind
        $bothRoles = DB::table('link as child_link')
            ->join('link as parent_link', static function ($join): void {
                $join->on('child_link.l_from', '=', 'parent_link.l_from')
                     ->on('child_link.l_file', '=', 'parent_link.l_file');
            })
            ->where('child_link.l_file', '=', $this->tree->id())
            ->where('child_link.l_type', '=', 'FAMC')
            ->where('parent_link.l_type', '=', 'FAMS')
            ->distinct()
            ->count('child_link.l_from');

        $this->assertGreaterThan(0, $bothRoles,
            'Es müssen Personen existieren, die sowohl Kind als auch Elternteil sind (Mehrgenerationen)');
    }
}
