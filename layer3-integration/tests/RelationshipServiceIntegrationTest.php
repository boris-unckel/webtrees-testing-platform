<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\RelationshipService;
use ReflectionMethod;

/**
 * Komponentenintegrationstest: RelationshipService mit MySQL.
 *
 * Testet getCloseRelationshipName() mit echten Personen aus demo.ged.
 * Ergänzt RelationshipDbTest (DB-Ebene) um Service-Ebene-Tests.
 *
 * legacyCousinName2() ist private static und nur über Reflection erreichbar,
 * da sie ausschließlich im Sprachzweig 'es' (Spanisch) aufgerufen wird.
 *
 * @covers \Fisharebest\Webtrees\Services\RelationshipService
 * @see docs/tds_conditions_ref.md S14, S16
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

    // --- AP1: legacyCousinName — Cousin-Pfade (S16) ---

    /**
     * S16 — Dritter Cousin: fatfatfatbrosonsonson triggert legacyCousinName(3).
     * Regex: (fat|fat|fat) bro (son|son|son) → up=3, down=3, cousin=3, removed=0.
     */
    public function test_legacy_name_algorithm_third_cousin(): void
    {
        $this->assertSame('third cousin', $this->relationship_service->legacyNameAlgorithm('fatfatfatbrosonsonson'));
    }

    /**
     * S16 — Vierter Cousin: legacyCousinName(4, 'U').
     * up=4, down=4, cousin=4, removed=0.
     */
    public function test_legacy_name_algorithm_fourth_cousin(): void
    {
        $this->assertSame('fourth cousin', $this->relationship_service->legacyNameAlgorithm('fatfatfatfatbrosonsonsonson'));
    }

    /**
     * S16 — Zweiter Cousin einmal entfernt (aufsteigend): removed=1, up>down.
     * up=3, down=2, cousin=2, removed=1.
     */
    public function test_legacy_name_algorithm_second_cousin_once_removed_ascending(): void
    {
        $result = $this->relationship_service->legacyNameAlgorithm('fatfatfatbrosonson');
        $this->assertStringContainsString('second cousin', $result);
        $this->assertStringContainsString('ascending', $result);
    }

    /**
     * S16 — Zweiter Cousin einmal entfernt (absteigend): removed=1, up<down.
     * up=2, down=3, cousin=2, removed=1.
     */
    public function test_legacy_name_algorithm_second_cousin_once_removed_descending(): void
    {
        $result = $this->relationship_service->legacyNameAlgorithm('fatfatbrosonsonson');
        $this->assertStringContainsString('second cousin', $result);
        $this->assertStringContainsString('descending', $result);
    }

    // --- AP7: legacyCousinName2 (private static, CRAP 462) via Reflection ---

    /**
     * legacyCousinName2 via Reflection — n=1, sex=M, relation=primo.
     *
     * Die Methode ist private static und nur im Sprachzweig 'es' erreichbar.
     * Reflection ermöglicht den direkten Aufruf unabhängig von I18N::languageTag().
     */
    public function test_legacy_cousin_name2_male_n1_returns_string(): void
    {
        $method = new ReflectionMethod(RelationshipService::class, 'legacyCousinName2');

        $result = $method->invoke(null, 1, 'M', 'primo');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * legacyCousinName2 via Reflection — n=1, sex=F, relation=prima.
     */
    public function test_legacy_cousin_name2_female_n1_returns_string(): void
    {
        $method = new ReflectionMethod(RelationshipService::class, 'legacyCousinName2');

        $result = $method->invoke(null, 1, 'F', 'prima');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * legacyCousinName2 via Reflection — n=2 (segundo primo).
     */
    public function test_legacy_cousin_name2_n2_returns_string(): void
    {
        $method = new ReflectionMethod(RelationshipService::class, 'legacyCousinName2');

        $result = $method->invoke(null, 2, 'M', 'primo');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * legacyCousinName2 via Reflection — n=3, sex=U (unbekannt).
     */
    public function test_legacy_cousin_name2_n3_unknown_sex_returns_string(): void
    {
        $method = new ReflectionMethod(RelationshipService::class, 'legacyCousinName2');

        $result = $method->invoke(null, 3, 'U', 'primo');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * legacyCousinName2 via Reflection — n=4 (cuarto primo).
     */
    public function test_legacy_cousin_name2_n4_returns_string(): void
    {
        $method = new ReflectionMethod(RelationshipService::class, 'legacyCousinName2');

        $result = $method->invoke(null, 4, 'M', 'primo');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

}
