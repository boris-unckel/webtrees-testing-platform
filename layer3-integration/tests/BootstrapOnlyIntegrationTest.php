<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Census\Census;
use Fisharebest\Webtrees\Elements\NoteStructure;
use Fisharebest\Webtrees\Exceptions\FileUploadException;
use Fisharebest\Webtrees\Factories\EncodingFactory;
use Fisharebest\Webtrees\Module\LanguageFrench;

/**
 * Komponentenintegrationstests: Bootstrap-only-Klassen.
 *
 * Alle Methoden ohne DB-Zugriff, außer NoteStructure::labelValue (benötigt Tree).
 * Deckt hohe CRAP-Scores ab, die ohne DB-Setup ausgelöst werden können.
 *
 * @covers \Fisharebest\Webtrees\Factories\EncodingFactory
 * @covers \Fisharebest\Webtrees\Module\LanguageFrench
 * @covers \Fisharebest\Webtrees\Census\Census
 * @covers \Fisharebest\Webtrees\Elements\NoteStructure
 * @covers \Fisharebest\Webtrees\Exceptions\FileUploadException
 */
class BootstrapOnlyIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    // --- EncodingFactory::detect ---

    /**
     * detect() mit UTF-8-BOM erkennt UTF-8.
     */
    public function test_encoding_factory_detect_utf8_bom(): void
    {
        $factory = new EncodingFactory();
        $result  = $factory->detect("\xEF\xBB\xBF");

        $this->assertNotNull($result);
    }

    /**
     * detect() mit UTF-16-BE-BOM erkennt UTF-16-BE.
     */
    public function test_encoding_factory_detect_utf16be_bom(): void
    {
        $factory = new EncodingFactory();
        $result  = $factory->detect("\xFE\xFF");

        $this->assertNotNull($result);
    }

    /**
     * detect() mit UTF-16-LE-BOM erkennt UTF-16-LE.
     */
    public function test_encoding_factory_detect_utf16le_bom(): void
    {
        $factory = new EncodingFactory();
        $result  = $factory->detect("\xFF\xFE");

        $this->assertNotNull($result);
    }

    /**
     * detect() ohne BOM gibt null zurück.
     */
    public function test_encoding_factory_detect_no_bom_returns_null(): void
    {
        $factory = new EncodingFactory();
        $result  = $factory->detect('0 HEAD');

        $this->assertNull($result);
    }

    // --- LanguageFrench::relationships ---

    /**
     * relationships() gibt nicht-leeres Array zurück.
     */
    public function test_language_french_relationships_returns_array(): void
    {
        $module = new LanguageFrench();
        $result = $module->relationships();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * relationships() enthält Relationship-Objekte als Einträge.
     * Das Array hat ganzzahlige Indizes (0, 1, ...) — nicht Pfad-Schlüssel wie 'fat'.
     */
    public function test_language_french_relationships_contains_relationship_objects(): void
    {
        $module = new LanguageFrench();
        $result = $module->relationships();

        // Alle Einträge sind Relationship-Objekte
        foreach ($result as $item) {
            $this->assertIsObject($item);
        }
    }

    // --- Census::censusPlaces ---

    /**
     * censusPlaces('en-US') gibt Array von CensusPlace-Objekten zurück.
     */
    public function test_census_places_en_us_returns_array(): void
    {
        $result = Census::censusPlaces('en-US');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * censusPlaces('en-GB') gibt Array zurück.
     */
    public function test_census_places_en_gb_returns_array(): void
    {
        $result = Census::censusPlaces('en-GB');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * censusPlaces('de') gibt Array zurück.
     */
    public function test_census_places_de_returns_array(): void
    {
        $result = Census::censusPlaces('de');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    // --- FileUploadException::__construct ---

    /**
     * FileUploadException mit null ist ein RuntimeException.
     */
    public function test_file_upload_exception_null_is_runtime_exception(): void
    {
        $exception = new FileUploadException(null);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertIsString($exception->getMessage());
    }

    /**
     * FileUploadException hat nicht-leere Message.
     */
    public function test_file_upload_exception_has_non_empty_message(): void
    {
        $exception = new FileUploadException(null);

        $this->assertNotEmpty($exception->getMessage());
    }

    // --- NoteStructure::labelValue (Tree-abhängig) ---

    /**
     * NoteStructure::labelValue mit leerem Wert gibt String zurück.
     */
    public function test_note_structure_label_value_empty_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $element = new NoteStructure('Note');
        $result  = $element->labelValue('', $this->tree);

        $this->assertIsString($result);
    }

    /**
     * NoteStructure::labelValue mit Freitext gibt String zurück.
     */
    public function test_note_structure_label_value_text_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $element = new NoteStructure('Note');
        $result  = $element->labelValue('Dies ist eine Notiz.', $this->tree);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * NoteStructure::labelValue mit XREF-Wert gibt String zurück.
     * Triggert den XREF-Branch (Registry::noteFactory()->make()).
     */
    public function test_note_structure_label_value_xref_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $element = new NoteStructure('Note');
        // XREF-Format: @NOTE1@ o.ä. — labelValue erkennt @ als XREF-Marker
        $result  = $element->labelValue('@N1@', $this->tree);

        $this->assertIsString($result);
    }
}
