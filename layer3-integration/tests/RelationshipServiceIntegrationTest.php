<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\RelationshipService;

/**
 * Komponentenintegrationstest: RelationshipService mit MySQL.
 *
 * Testet getCloseRelationshipName() mit echten Personen aus demo.ged.
 * Ergänzt RelationshipDbTest (DB-Ebene) um Service-Ebene-Tests.
 *
 * @covers \Fisharebest\Webtrees\Services\RelationshipService
 * @see docs/testing-bigpicture.md S14, S16
 */
class RelationshipServiceIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private RelationshipService $relationship_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relationship_service = new RelationshipService();
    }

    /**
     * Ehepartner-Beziehung: Elizabeth II → Philip = husband.
     */
    public function test_close_relationship_name_for_spouses(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $elizabeth = Registry::individualFactory()->make('X1030', $this->tree);
        $husband   = Registry::individualFactory()->make('X1041', $this->tree);

        $this->assertNotNull($elizabeth);
        $this->assertNotNull($husband);

        $name = $this->relationship_service->getCloseRelationshipName($elizabeth, $husband);
        $this->assertSame('husband', $name);
    }

    /**
     * Eltern-Kind-Beziehung: Elizabeth II → Sohn = son.
     */
    public function test_close_relationship_name_for_parent_child(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $elizabeth = Registry::individualFactory()->make('X1030', $this->tree);
        $son       = Registry::individualFactory()->make('X1052', $this->tree);

        $this->assertNotNull($elizabeth);
        $this->assertNotNull($son);

        $name = $this->relationship_service->getCloseRelationshipName($elizabeth, $son);
        $this->assertSame('son', $name);
    }

    /**
     * Gleiche Person: Elizabeth II → Elizabeth II = herself.
     */
    public function test_close_relationship_name_for_same_person(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $elizabeth = Registry::individualFactory()->make('X1030', $this->tree);

        $this->assertNotNull($elizabeth);

        $name = $this->relationship_service->getCloseRelationshipName($elizabeth, $elizabeth);
        $this->assertSame('herself', $name);
    }

    /**
     * Umgekehrte Richtung: Sohn → Elizabeth II = mother.
     */
    public function test_close_relationship_name_reverse_direction(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $elizabeth = Registry::individualFactory()->make('X1030', $this->tree);
        $son       = Registry::individualFactory()->make('X1052', $this->tree);

        $this->assertNotNull($elizabeth);
        $this->assertNotNull($son);

        $name = $this->relationship_service->getCloseRelationshipName($son, $elizabeth);
        $this->assertSame('mother', $name);
    }

    // --- AP: S16 legacyNameAlgorithm — direkte Pfad-Strings ---

    /**
     * S16 — Einfache Beziehungs-Pfade: Vater, Mutter, Bruder, Schwester.
     */
    public function test_legacy_name_algorithm_direct_relationships(): void
    {
        $this->assertSame('father',  $this->relationship_service->legacyNameAlgorithm('fat'));
        $this->assertSame('mother',  $this->relationship_service->legacyNameAlgorithm('mot'));
        $this->assertSame('brother', $this->relationship_service->legacyNameAlgorithm('bro'));
        $this->assertSame('sister',  $this->relationship_service->legacyNameAlgorithm('sis'));
        $this->assertSame('son',     $this->relationship_service->legacyNameAlgorithm('son'));
        $this->assertSame('daughter',$this->relationship_service->legacyNameAlgorithm('dau'));
    }

    /**
     * S16 — Onkel/Tante: Vaterlinie und Mutterlinie.
     */
    public function test_legacy_name_algorithm_uncle_aunt(): void
    {
        $this->assertSame('uncle', $this->relationship_service->legacyNameAlgorithm('fatbro'));
        $this->assertSame('aunt',  $this->relationship_service->legacyNameAlgorithm('fatsis'));
        $this->assertSame('uncle', $this->relationship_service->legacyNameAlgorithm('motbro'));
        $this->assertSame('aunt',  $this->relationship_service->legacyNameAlgorithm('motsis'));
    }

    /**
     * S16 — Großeltern: alle vier Kombinationen.
     */
    public function test_legacy_name_algorithm_grandparents(): void
    {
        $this->assertSame('paternal grandfather', $this->relationship_service->legacyNameAlgorithm('fatfat'));
        $this->assertSame('paternal grandmother', $this->relationship_service->legacyNameAlgorithm('fatmot'));
        $this->assertSame('maternal grandfather', $this->relationship_service->legacyNameAlgorithm('motfat'));
        $this->assertSame('maternal grandmother', $this->relationship_service->legacyNameAlgorithm('motmot'));
    }

    /**
     * S16 — Ehemann/Ehefrau.
     */
    public function test_legacy_name_algorithm_spouse(): void
    {
        $this->assertSame('husband', $this->relationship_service->legacyNameAlgorithm('husb'));
        $this->assertSame('wife',    $this->relationship_service->legacyNameAlgorithm('wife'));
    }
}
