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
 * @see docs/testing-bigpicture.md G05
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
}
