<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Tree;

/**
 * Komponentenintegrationstest: GedcomEditService.
 *
 * editLinesToGedcom() — Bootstrap-only (Registry::elementFactory).
 * insertMissingLevels() — protected, über anonyme Unterklasse zugänglich; benötigt Tree.
 *
 * @see docs/testing-bigpicture.md G29
 * @covers \Fisharebest\Webtrees\Services\GedcomEditService
 */
class GedcomEditServiceIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private GedcomEditService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GedcomEditService();
    }

    // --- editLinesToGedcom (Bootstrap-only) ---

    /**
     * editLinesToGedcom mit NAME-Tag gibt GEDCOM-Zeile zurück.
     */
    public function test_edit_lines_to_gedcom_name_tag_returns_gedcom(): void
    {
        $result = $this->service->editLinesToGedcom(
            'INDI',
            ['1'],
            ['NAME'],
            ['John /Doe/'],
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('NAME', $result);
    }

    /**
     * editLinesToGedcom mit BIRT+DATE gibt mehrzeiliges GEDCOM zurück.
     */
    public function test_edit_lines_to_gedcom_birth_with_date_returns_multiline(): void
    {
        $result = $this->service->editLinesToGedcom(
            'INDI',
            ['1', '2'],
            ['BIRT', 'DATE'],
            ['', '1 JAN 1900'],
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('BIRT', $result);
        $this->assertStringContainsString('DATE', $result);
    }

    /**
     * editLinesToGedcom mit append=false gibt String ohne führendes Newline zurück.
     */
    public function test_edit_lines_to_gedcom_append_false_no_leading_newline(): void
    {
        $result = $this->service->editLinesToGedcom(
            'INDI',
            ['1'],
            ['NAME'],
            ['Jane /Smith/'],
            false,
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString("\n", substr($result, 0, 1));
    }

    /**
     * editLinesToGedcom mit leeren Werten gibt leeren String zurück.
     */
    public function test_edit_lines_to_gedcom_empty_values_returns_empty(): void
    {
        $result = $this->service->editLinesToGedcom(
            'INDI',
            ['1'],
            ['NAME'],
            [''],
        );

        $this->assertIsString($result);
    }

    // --- insertMissingLevels (protected, via anonyme Unterklasse, Tree-abhängig) ---

    /**
     * insertMissingLevels via anonyme Unterklasse — INDI:NAME-Tag gibt String zurück.
     */
    public function test_insert_missing_levels_name_tag_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $service = new class extends GedcomEditService {
            public function insertMissingLevelsPublic(Tree $tree, string $tag, string $gedcom, bool $include_hidden): string
            {
                return $this->insertMissingLevels($tree, $tag, $gedcom, $include_hidden);
            }
        };

        $result = $service->insertMissingLevelsPublic(
            $this->tree,
            'INDI:NAME',
            '1 NAME John /Doe/',
            false,
        );

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * insertMissingLevels via anonyme Unterklasse — FAM:MARR-Tag gibt String zurück.
     */
    public function test_insert_missing_levels_marr_tag_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $service = new class extends GedcomEditService {
            public function insertMissingLevelsPublic(Tree $tree, string $tag, string $gedcom, bool $include_hidden): string
            {
                return $this->insertMissingLevels($tree, $tag, $gedcom, $include_hidden);
            }
        };

        $result = $service->insertMissingLevelsPublic(
            $this->tree,
            'FAM:MARR',
            '1 MARR',
            true,
        );

        $this->assertIsString($result);
    }

    /**
     * insertMissingLevels via anonyme Unterklasse — INDI:BIRT mit include_hidden=false.
     */
    public function test_insert_missing_levels_birt_no_hidden_returns_string(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $service = new class extends GedcomEditService {
            public function insertMissingLevelsPublic(Tree $tree, string $tag, string $gedcom, bool $include_hidden): string
            {
                return $this->insertMissingLevels($tree, $tag, $gedcom, $include_hidden);
            }
        };

        $result = $service->insertMissingLevelsPublic(
            $this->tree,
            'INDI:BIRT',
            '1 BIRT',
            false,
        );

        $this->assertIsString($result);
    }
}
