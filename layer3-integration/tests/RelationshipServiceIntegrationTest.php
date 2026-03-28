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
 * @see docs/testing-bigpicture.md S14
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
}
