<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Services\GedcomService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: GedcomService-Utility-Methoden.
 *
 * Reine Logik-Tests (kein DB-Zugriff), aber laufen im Container
 * für einheitliche PHP-Version und Trace-Erfassung.
 *
 * @covers \Fisharebest\Webtrees\Services\GedcomService
 * @see docs/tds_conditions_ref.md G05
 */
class GedcomServiceIntegrationTest extends MysqlTestCase
{
    private GedcomService $gedcom_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gedcom_service = new GedcomService();
    }

    public function test_canonical_tag_returns_standard_tags(): void
    {
        $this->assertSame('INDI', $this->gedcom_service->canonicalTag('INDI'));
        $this->assertSame('FAM', $this->gedcom_service->canonicalTag('FAM'));
        $this->assertSame('SOUR', $this->gedcom_service->canonicalTag('SOUR'));
        $this->assertSame('NOTE', $this->gedcom_service->canonicalTag('NOTE'));
    }

    public function test_canonical_tag_converts_long_form_to_abbreviation(): void
    {
        $this->assertSame('ABBR', $this->gedcom_service->canonicalTag('ABBREVIATION'));
    }

    public function test_canonical_tag_is_case_insensitive(): void
    {
        $this->assertSame('INDI', $this->gedcom_service->canonicalTag('indi'));
        $this->assertSame('FAM', $this->gedcom_service->canonicalTag('fam'));
    }

    public function test_canonical_tag_handles_synonyms(): void
    {
        $this->assertSame('NOTE', $this->gedcom_service->canonicalTag('NOTE'));
    }

    public function test_canonical_tag_returns_unknown_tags_unchanged(): void
    {
        $this->assertSame('_CUSTOM', $this->gedcom_service->canonicalTag('_CUSTOM'));
    }

    public function test_read_latitude_with_north(): void
    {
        $this->assertEqualsWithDelta(51.5, $this->gedcom_service->readLatitude('N51.5'), 0.001);
    }

    public function test_read_latitude_with_south(): void
    {
        $this->assertEqualsWithDelta(-33.9, $this->gedcom_service->readLatitude('S33.9'), 0.001);
    }

    public function test_read_latitude_with_plain_number(): void
    {
        $this->assertEqualsWithDelta(48.1, $this->gedcom_service->readLatitude('48.1'), 0.001);
    }

    public function test_read_longitude_with_east(): void
    {
        $this->assertEqualsWithDelta(13.4, $this->gedcom_service->readLongitude('E13.4'), 0.001);
    }

    public function test_read_longitude_with_west(): void
    {
        $this->assertEqualsWithDelta(-73.9, $this->gedcom_service->readLongitude('W73.9'), 0.001);
    }

    public function test_read_latitude_invalid_returns_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->gedcom_service->readLatitude('invalid'), 0.001);
    }

    /**
     * @group ported-l2-doubles
     */
    public function test_canonical_tag_converts_birth_death_marriage_individual(): void
    {
        // Arrange — service from setUp.

        // Act + Assert — long-form GEDCOM tag names are mapped to canonical abbreviations.
        $this->assertSame('BIRT', $this->gedcom_service->canonicalTag('BIRTH'));
        $this->assertSame('DEAT', $this->gedcom_service->canonicalTag('DEATH'));
        $this->assertSame('MARR', $this->gedcom_service->canonicalTag('MARRIAGE'));
        $this->assertSame('INDI', $this->gedcom_service->canonicalTag('INDIVIDUAL'));
        // Case-insensitivity also for long-form input.
        $this->assertSame('BIRT', $this->gedcom_service->canonicalTag('birth'));
    }

    /**
     * @group ported-l2-doubles
     */
    public function test_canonical_tag_handles_pgv_synonyms(): void
    {
        // Arrange — service from setUp.

        // Act + Assert — legacy PhpGedView tags map to webtrees tags via TAG_SYNONYMS.
        $this->assertSame('_WT_USER', $this->gedcom_service->canonicalTag('_PGVU'));
        $this->assertSame('_WT_OBJE_SORT', $this->gedcom_service->canonicalTag('_PGV_OBJS'));
    }

    /**
     * @group ported-l2-doubles
     */
    public function test_canonical_tag_passes_through_already_canonical_and_lowercase_custom(): void
    {
        // Arrange — service from setUp.

        // Act + Assert — canonical tags pass through; unknown tags are upper-cased only.
        $this->assertSame('BIRT', $this->gedcom_service->canonicalTag('BIRT'));
        $this->assertSame('CUSTOM', $this->gedcom_service->canonicalTag('custom'));
    }

    /**
     * @group ported-l2-doubles
     */
    public function test_read_latitude_returns_null_for_invalid_and_empty(): void
    {
        // Arrange — service from setUp.

        // Act + Assert — non-numeric and empty input yields null (no silent default).
        $this->assertNull($this->gedcom_service->readLatitude('invalid'));
        $this->assertNull($this->gedcom_service->readLatitude(''));
    }

    /**
     * @group ported-l2-doubles
     */
    public function test_read_longitude_returns_null_for_invalid(): void
    {
        // Arrange — service from setUp.

        // Act + Assert — non-numeric longitude input yields null.
        $this->assertNull($this->gedcom_service->readLongitude('invalid'));
    }
}
