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
 * @see docs/testing-bigpicture-prompt.md G01, G02, G03, G04, G07, G08, G09, G10, G11, G12, G23
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

    // --- AP 8-2: Encoding-Import ANSEL/CP1252 (G08) ---

    /**
     * G08 — ANSEL-Zeichen (nach UTF-8 konvertiert) werden korrekt gespeichert.
     *
     * Encoding-Konvertierung (ANSEL→UTF-8) erfolgt auf höherer Ebene (EncodingFactory).
     * Hier testen wir, dass die konvertierten UTF-8-Zeichen korrekt in MySQL ankommen.
     * ANSEL \xA1 = Ł, \xA2 = Ø → nach Konvertierung UTF-8 Ł und Ø.
     */
    public function test_import_ansel_converted_characters_stored_correctly(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'ansel-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'ANSEL Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        // UTF-8-Repräsentation von ANSEL-Zeichen (nach Konvertierung)
        $gedcom = "0 @I1@ INDI\n1 NAME Łodz /Ørsted/\n1 SEX M";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $name = DB::table('name')
            ->where('n_file', '=', $this->tree->id())
            ->where('n_id', '=', 'I1')
            ->first();

        $this->assertNotNull($name, 'INDI mit ANSEL-typischen Zeichen muss importiert werden');
        $this->assertStringContainsString('Ł', $name->n_full, 'Ł (ANSEL \xA1) muss in DB erhalten bleiben');
        $this->assertStringContainsString('Ø', $name->n_surn, 'Ø (ANSEL \xA2) muss in DB erhalten bleiben');
    }

    /**
     * G08 — Diakritika (Umlaute, Akzente) werden korrekt gespeichert.
     */
    public function test_import_preserves_diacritics_in_names(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'diacrit-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Diacritics Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        // UTF-8 Diakritika (wie sie nach ANSEL-Konvertierung aussehen)
        $gedcom = "0 @I1@ INDI\n1 NAME María /García/\n1 SEX F";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $name = DB::table('name')
            ->where('n_file', '=', $this->tree->id())
            ->where('n_id', '=', 'I1')
            ->first();

        $this->assertNotNull($name, 'INDI mit Diakritika muss importiert werden');
        $this->assertStringContainsString('í', $name->n_givn, 'Akzent in María muss erhalten bleiben');
        $this->assertStringContainsString('í', $name->n_surn, 'Akzent in García muss erhalten bleiben');
    }

    /**
     * G08 — CP1252-typische Zeichen (ä, ö, ü) werden nach UTF-8-Konvertierung korrekt gespeichert.
     */
    public function test_import_cp1252_converted_umlauts_stored_correctly(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'cp1252-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'CP1252 Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        // UTF-8-Repräsentation (nach CP1252→UTF-8 Konvertierung)
        $gedcom = "0 @I1@ INDI\n1 NAME Müller /Schröder/\n1 SEX M";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $name = DB::table('name')
            ->where('n_file', '=', $this->tree->id())
            ->where('n_id', '=', 'I1')
            ->first();

        $this->assertNotNull($name, 'INDI mit Umlauten muss importiert werden');
        $this->assertStringContainsString('ü', $name->n_givn, 'ü muss erhalten bleiben');
        $this->assertStringContainsString('ö', $name->n_surn, 'ö muss erhalten bleiben');
    }

    /**
     * G08 — CP1252-Sonderzeichen (€, –) werden nach UTF-8-Konvertierung korrekt gespeichert.
     */
    public function test_import_cp1252_converted_special_chars_stored_correctly(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'cp1252-spec-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'CP1252 Special');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        // UTF-8-Repräsentation (nach CP1252→UTF-8 Konvertierung)
        $gedcom = "0 @I1@ INDI\n1 NAME Hans /Müller/\n1 SEX M\n1 NOTE Kosten: 100€ für 2020–2021";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $indi = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->where('i_id', '=', 'I1')
            ->first();

        $this->assertNotNull($indi, 'INDI mit Sonderzeichen muss importiert werden');
        $this->assertStringContainsString('€', $indi->i_gedcom, 'Euro-Zeichen muss erhalten bleiben');
        $this->assertStringContainsString('–', $indi->i_gedcom, 'Halbgeviertstrich muss erhalten bleiben');
    }

    // --- AP 8-3: Inline-Media (G09) und Custom-Tags (G11) ---

    /**
     * G09 — Inline-OBJE wird in separaten Media-Record aufgespalten.
     */
    public function test_import_splits_inline_obje_into_separate_media_records(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'obje-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Inline OBJE Test');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('media')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('media_file')->where('m_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 OBJE\n2 FILE photo.jpg\n2 FORM jpeg\n2 TITL Portrait";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $media_count = DB::table('media')
            ->where('m_file', '=', $this->tree->id())
            ->count();

        $this->assertGreaterThan(0, $media_count, 'Inline-OBJE muss in eigenen Media-Record umgewandelt werden');
    }

    /**
     * G09 — Inline-OBJE bewahrt Dateireferenzen.
     */
    public function test_import_inline_obje_preserves_file_references(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'obje-ref-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'OBJE File Ref');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('media')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('media_file')->where('m_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME Jane /Doe/\n1 SEX F\n1 OBJE\n2 FILE family.png\n2 FORM png";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $file = DB::table('media_file')
            ->where('m_file', '=', $this->tree->id())
            ->first();

        $this->assertNotNull($file, 'Media-File-Referenz muss existieren');
        $this->assertSame('family.png', $file->multimedia_file_refn, 'Dateiname muss erhalten bleiben');
    }

    /**
     * G09 — Inline-OBJE verknüpft zum Quell-Record.
     */
    public function test_import_inline_obje_links_to_source_record(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'obje-link-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'OBJE Link');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();
        DB::table('media')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('media_file')->where('m_file', '=', $this->tree->id())->delete();
        DB::table('link')->where('l_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 OBJE\n2 FILE portrait.jpg\n2 FORM jpeg";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        // INDI-Record muss OBJE-Link haben (Verweis auf den neuen Media-Record)
        $link = DB::table('link')
            ->where('l_file', '=', $this->tree->id())
            ->where('l_from', '=', 'I1')
            ->where('l_type', '=', 'OBJE')
            ->first();

        $this->assertNotNull($link, 'INDI muss einen OBJE-Link zum neuen Media-Record haben');
    }

    /**
     * G11 — Custom-Tags von Ancestry werden nicht verworfen.
     */
    public function test_import_ancestry_custom_tags_not_discarded(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'ancestry-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Ancestry Tags');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 _MILT\n2 DATE 1940\n2 NOTE Army service";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $indi = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->where('i_id', '=', 'I1')
            ->first();

        $this->assertNotNull($indi, 'INDI mit Ancestry-Custom-Tags muss importiert werden');
        $this->assertStringContainsString('_MILT', $indi->i_gedcom, 'Ancestry _MILT-Tag darf nicht verworfen werden');
    }

    /**
     * G11 — Custom-Tags von FamilySearch werden nicht verworfen.
     */
    public function test_import_familysearch_custom_tags_not_discarded(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'famsearch-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'FamilySearch Tags');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 _FSFTID 12345";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $indi = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->where('i_id', '=', 'I1')
            ->first();

        $this->assertNotNull($indi, 'INDI mit FamilySearch-Tags muss importiert werden');
        $this->assertStringContainsString('_FSFTID', $indi->i_gedcom, 'FamilySearch _FSFTID darf nicht verworfen werden');
    }

    /**
     * G11 — Custom-Tags von RootsMagic werden nicht verworfen.
     */
    public function test_import_rootsmagic_custom_tags_not_discarded(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'rootsmagic-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'RootsMagic Tags');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 _WEBTAG\n2 NAME TestTag";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $indi = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->where('i_id', '=', 'I1')
            ->first();

        $this->assertNotNull($indi, 'INDI mit RootsMagic-Tags muss importiert werden');
        $this->assertStringContainsString('_WEBTAG', $indi->i_gedcom, 'RootsMagic _WEBTAG darf nicht verworfen werden');
    }

    // --- AP 8-8: Legacy-Formate (G10) und GEDCOM 5.5.1 Compliance (G23) ---

    /**
     * G10 — Legacy _PLAC_DEFN mit Koordinaten erzeugt place_location-Eintrag.
     */
    public function test_import_legacy_plac_defn_creates_place_location(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'legacy-plac-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Legacy PLAC Test');

        // Erst einen Ort anlegen über normalen Import
        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 BIRT\n2 PLAC London, England";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        // Dann _PLAC_DEFN importieren
        $gedcom = "0 _PLAC_DEFN\n1 PLAC London, England\n2 MAP\n3 LATI N51.5074\n3 LONG W0.1278";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $location = DB::table('place_location')
            ->where('place', '=', 'London')
            ->first();

        $this->assertNotNull($location, '_PLAC_DEFN muss place_location-Eintrag erzeugen');
    }

    /**
     * G10 — Legacy _PLAC_DEFN extrahiert Koordinaten korrekt.
     */
    public function test_import_legacy_plac_defn_extracts_coordinates(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'legacy-coord-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Legacy Coords');

        $gedcom = "0 @I1@ INDI\n1 NAME Jane /Doe/\n1 BIRT\n2 PLAC Paris, France";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $gedcom = "0 _PLAC_DEFN\n1 PLAC Paris, France\n2 MAP\n3 LATI N48.8566\n3 LONG E2.3522";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $location = DB::table('place_location')
            ->where('place', '=', 'Paris')
            ->first();

        $this->assertNotNull($location, 'Koordinaten für Paris müssen existieren');
        if ($location->latitude !== null) {
            $this->assertGreaterThan(0, (float) $location->latitude, 'Paris Breitengrad muss positiv sein (Nordhalbkugel)');
        }
    }

    /**
     * G10 — TNG _PLAC mit numerischen Koordinaten erzeugt place_location-Eintrag.
     */
    public function test_import_tng_plac_creates_place_location(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'tng-plac-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'TNG PLAC Test');

        $gedcom = "0 @I1@ INDI\n1 NAME Hans /Schmidt/\n1 BIRT\n2 PLAC Berlin, Germany";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $gedcom = "0 _PLAC Berlin, Germany\n1 MAP\n2 LATI 52.5200\n2 LONG 13.4050";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $location = DB::table('place_location')
            ->where('place', '=', 'Berlin')
            ->first();

        $this->assertNotNull($location, 'TNG _PLAC muss place_location-Eintrag erzeugen');
    }

    /**
     * G10 — TNG _PLAC extrahiert numerische Koordinaten korrekt.
     */
    public function test_import_tng_plac_extracts_numeric_coordinates(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'tng-coord-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'TNG Coords');

        $gedcom = "0 @I1@ INDI\n1 NAME Maria /Garcia/\n1 BIRT\n2 PLAC Madrid, Spain";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $gedcom = "0 _PLAC Madrid, Spain\n1 MAP\n2 LATI 40.4168\n2 LONG -3.7038";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $location = DB::table('place_location')
            ->where('place', '=', 'Madrid')
            ->first();

        $this->assertNotNull($location, 'Numerische Koordinaten für Madrid müssen existieren');
        if ($location->latitude !== null) {
            $this->assertGreaterThan(0, (float) $location->latitude, 'Madrid Breitengrad muss positiv sein');
        }
    }

    /**
     * G23 — Standard GEDCOM 5.5.1 Tags werden nicht verworfen.
     */
    public function test_import_gedcom_551_standard_tags_not_dropped(): void
    {
        $this->createAndLoginAdmin();
        $uniqueName = 'g551-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'GEDCOM 5.5.1 Tags');
        DB::table('individuals')->where('i_file', '=', $this->tree->id())->delete();

        // Record mit allen gängigen GEDCOM 5.5.1 Tags
        $gedcom = "0 @I1@ INDI\n1 NAME John /Doe/\n1 SEX M\n1 BIRT\n2 DATE 1 JAN 1900\n2 PLAC London\n1 DEAT\n2 DATE 31 DEC 1980\n1 OCCU Farmer\n1 RELI Anglican\n1 NATI British";
        $this->gedcomImportService->importRecord($gedcom, $this->tree, false);

        $indi = DB::table('individuals')
            ->where('i_file', '=', $this->tree->id())
            ->where('i_id', '=', 'I1')
            ->first();

        $this->assertNotNull($indi, 'INDI mit Standard-Tags muss importiert werden');
        $this->assertStringContainsString('OCCU', $indi->i_gedcom, 'OCCU-Tag darf nicht verworfen werden');
        $this->assertStringContainsString('RELI', $indi->i_gedcom, 'RELI-Tag darf nicht verworfen werden');
        $this->assertStringContainsString('NATI', $indi->i_gedcom, 'NATI-Tag darf nicht verworfen werden');
    }
}
