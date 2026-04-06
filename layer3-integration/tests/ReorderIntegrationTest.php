<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderChildrenPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderFamiliesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderNamesPage;

/**
 * Komponentenintegrationstest: Sortierung (Reorder) — E06.
 *
 * Tests:
 * - ReorderChildrenPage GET: gültige FAM-XREF + Manager-Auth → 200
 * - ReorderChildrenPage GET: ungültige FAM-XREF → HttpNotFoundException
 * - ReorderNamesPage GET: gültige INDI-XREF + Manager-Auth → 200
 * - ReorderFamiliesPage GET: gültige INDI-XREF → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderChildrenPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderNamesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderFamiliesPage
 * @see docs/testquality_improve_E06.md
 */
class ReorderIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e06-reorder', 'E06 Reorder', self::DEMO_GED);
    }

    /**
     * EP1: ReorderChildrenPage GET mit gültiger FAM-XREF → 200.
     */
    public function test_reorder_children_page_with_valid_family_returns_200(): void
    {
        $handler = new ReorderChildrenPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'f1',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: ReorderChildrenPage GET mit ungültiger FAM-XREF → HttpNotFoundException.
     */
    public function test_reorder_children_page_with_unknown_family_throws_not_found(): void
    {
        $handler = new ReorderChildrenPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'DOESNOTEXIST',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP3: ReorderNamesPage GET mit gültiger INDI-XREF → 200.
     */
    public function test_reorder_names_page_with_valid_individual_returns_200(): void
    {
        $handler = new ReorderNamesPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: ReorderFamiliesPage GET mit gültiger INDI-XREF → 200.
     */
    public function test_reorder_families_page_with_valid_individual_returns_200(): void
    {
        $handler = new ReorderFamiliesPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
