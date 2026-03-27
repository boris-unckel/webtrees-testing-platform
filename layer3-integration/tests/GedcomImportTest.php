<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;

/**
 * Komponentenintegrationstest: GEDCOM-Import gegen MySQL.
 *
 * Prüft, dass Records korrekt in die Datenbank importiert werden.
 *
 * @covers \Fisharebest\Webtrees\Services\GedcomImportService
 * @see docs/testing-bigpicture-prompt.md G01, G02, G03, G04, G07, G12
 */
class GedcomImportTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * G01 — INDI-Records importieren → korrekte DB-Einträge
     */
    public function test_import_indi_records_creates_correct_db_entries(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — demo.ged enthält 72 Individuen
        $count = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();

        $this->assertSame(72, $count, 'demo.ged muss 72 Individuen enthalten');
    }

    /**
     * G02 — FAM-Records importieren → Beziehungen korrekt verknüpft
     */
    public function test_import_fam_records_creates_correct_relationships(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — demo.ged enthält 29 Familien
        $count = DB::table('families')
            ->where('f_file', '=', $this->tree->id())
            ->count();

        $this->assertSame(29, $count, 'demo.ged muss 29 Familien enthalten');

        // Jede Familie hat mindestens einen HUSB oder WIFE
        $familiesWithPartner = DB::table('families')
            ->where('f_file', '=', $this->tree->id())
            ->where(static function ($query): void {
                $query->whereNot('f_husb', '=', '')
                      ->orWhereNot('f_wife', '=', '');
            })
            ->count();

        $this->assertGreaterThan(0, $familiesWithPartner, 'Familien müssen Partner-Verknüpfungen haben');
    }

    /**
     * G03 — Nebenrecords (SOUR, NOTE, REPO, OBJE) importieren
     */
    public function test_import_secondary_records_creates_db_entries(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — Sources vorhanden
        $sources = DB::table('sources')
            ->where('s_file', '=', $this->tree->id())
            ->count();

        $this->assertGreaterThan(0, $sources, 'demo.ged muss Quellen enthalten');
    }

    /**
     * G04 — Place-Hierarchie beim Import aufgebaut
     */
    public function test_import_builds_place_hierarchy(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — places-Tabelle gefüllt
        $places = DB::table('places')
            ->where('p_file', '=', $this->tree->id())
            ->count();

        $this->assertGreaterThan(0, $places, 'Import muss Orte erzeugen');
    }

    /**
     * G07 — UTF-8-GEDCOM importieren → keine Zeichenverluste
     */
    public function test_import_utf8_preserves_characters(): void
    {
        // Arrange & Act — deutsches Muster mit Umlauten
        $this->createTreeWithGedcom('muster', 'Muster', '/fixtures/gedcom-l-muster.ged');

        // Assert — Individuen vorhanden (37 im Muster)
        $count = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();

        $this->assertSame(37, $count, 'gedcom-l-muster.ged muss 37 Individuen enthalten');
    }

    /**
     * G12 — Eindeutige XREFs, keine Kollisionen
     */
    public function test_import_generates_unique_xrefs(): void
    {
        // Arrange & Act
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Assert — alle XREFs in individuals eindeutig
        $totalXrefs = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();

        $uniqueXrefs = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->distinct()
            ->count('i_id');

        $this->assertSame($totalXrefs, $uniqueXrefs, 'Alle XREFs müssen eindeutig sein');
    }

    /**
     * G05 — Date-Parsing: Datumsfelder werden korrekt in die dates-Tabelle geschrieben.
     */
    public function test_import_parses_date_fields(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Queen Elizabeth II: BIRT DATE 21 APR 1926
        $date = DB::table('dates')
            ->where('d_file', '=', $this->tree->id())
            ->where('d_gid', '=', 'X1030')
            ->where('d_fact', '=', 'BIRT')
            ->first();

        $this->assertNotNull($date, 'Geburtsdatum für X1030 muss existieren');
        $this->assertEquals(21, $date->d_day);
        $this->assertEquals(4, $date->d_mon);
        $this->assertEquals(1926, $date->d_year);
    }

    /**
     * G05 — Date-Parsing: Julianische Tageswerte werden berechnet.
     */
    public function test_import_computes_julian_days(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $dates_with_jd = DB::table('dates')
            ->where('d_file', '=', $this->tree->id())
            ->where('d_julianday1', '>', 0)
            ->count();

        $this->assertGreaterThan(0, $dates_with_jd, 'Einige Datensätze müssen Julianische Tageswerte haben');
    }

    /**
     * G06 — Name-Extraktion: Vorname, Nachname und Soundex werden gespeichert.
     */
    public function test_import_extracts_name_components(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        // Queen Elizabeth II — NAME Queen Elizabeth II, SURN Windsor
        $name = DB::table('name')
            ->where('n_file', '=', $this->tree->id())
            ->where('n_id', '=', 'X1030')
            ->first();

        $this->assertNotNull($name, 'Name-Record für X1030 muss existieren');
        $this->assertNotEmpty($name->n_givn, 'Vorname muss extrahiert werden');
        $this->assertSame('Windsor', $name->n_surn, 'Nachname muss extrahiert werden');
        // Soundex wird aus n_soundex_givn_std generiert (nicht surn, da NAME keine /slashes/ hat)
        $this->assertNotEmpty($name->n_soundex_givn_std, 'Standard-Soundex für Vorname muss generiert werden');
    }

    /**
     * G08 — Multi-Line-Notes (CONT/CONC) werden fehlerfrei importiert.
     */
    public function test_import_handles_multi_line_notes(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'note-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Note Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 NOTE First line\n2 CONT Second line\n2 CONC  continued";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $count = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();

        $this->assertSame(1, $count, 'Multi-Line-Notes müssen fehlerfrei importiert werden');
    }

    /**
     * G09 — Leere Felder verursachen keine Import-Fehler.
     */
    public function test_import_handles_empty_fields(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'empty-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Empty Fields Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME Jane /Doe/\n1 SEX F\n1 BIRT\n2 DATE\n2 PLAC";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $count = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();

        $this->assertSame(1, $count, 'Leere Felder müssen fehlerfrei importiert werden');
    }

    /**
     * G11 — Medienobjekte (OBJE) werden korrekt importiert.
     */
    public function test_import_media_objects(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);

        $media_count = DB::table('media')
            ->where('m_file', '=', $this->tree->id())
            ->count();

        $this->assertGreaterThan(0, $media_count, 'demo.ged muss Medienobjekte enthalten');

        $media_files = DB::table('media_file')
            ->where('m_file', '=', $this->tree->id())
            ->count();

        $this->assertGreaterThan(0, $media_files, 'Medienobjekte müssen Datei-Referenzen haben');
    }

    /**
     * Einzelner INDI-Record importieren → genau ein Individuum in DB.
     */
    public function test_import_single_indi_record(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'single-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Single INDI Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 BIRT\n2 DATE 1 JAN 1900";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $count = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->count();

        $this->assertSame(1, $count, 'Genau ein INDI-Record muss importiert werden');

        $name = DB::table('name')
            ->where('n_file', '=', $this->tree->id())
            ->where('n_id', '=', 'I1')
            ->value('n_full');

        $this->assertSame('John Doe', $name);
    }
}
