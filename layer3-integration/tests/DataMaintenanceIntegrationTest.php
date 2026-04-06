<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\DataFixChoose;
use Fisharebest\Webtrees\Http\RequestHandlers\DataFixPage;
use Fisharebest\Webtrees\Http\RequestHandlers\FindDuplicateRecords;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;

/**
 * Komponentenintegrationstest: Datenpflege-Werkzeuge — A09.
 *
 * Tests:
 * - FindDuplicateRecords GET → 200
 * - DataFixPage GET: kein spezifisches Modul → 200 (Auswahl-View)
 * - DataFixPage GET: spezifisches Modul → 200 (DataFix-Seite)
 * - DataFixChoose GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\FindDuplicateRecords
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DataFixPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DataFixChoose
 * @see docs/testquality_improve_A09.md
 */
class DataMaintenanceIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('a09-datafix', 'A09 DataFix', self::DEMO_GED);
    }

    /**
     * EP1: FindDuplicateRecords GET mit demo.ged-Baum → 200.
     */
    public function test_find_duplicate_records_returns_200(): void
    {
        $handler = new FindDuplicateRecords(new AdminService());

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: DataFixPage GET: kein spezifisches Modul (data_fix='') → 200, Auswahl-View.
     */
    public function test_data_fix_page_selection_view_returns_200(): void
    {
        $handler = new DataFixPage(new ModuleService());

        $request = $this->createRequest(
            attributes: [
                'tree'     => $this->tree,
                'data_fix' => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP3: DataFixPage GET: spezifisches Modul ('fix-place-names') → 200, DataFix-Seite.
     */
    public function test_data_fix_page_with_module_returns_200(): void
    {
        $handler = new DataFixPage(new ModuleService());

        $request = $this->createRequest(
            attributes: [
                'tree'     => $this->tree,
                'data_fix' => 'fix-place-names',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: DataFixChoose GET → 200.
     */
    public function test_data_fix_choose_returns_200(): void
    {
        $handler = new DataFixChoose(new ModuleService());

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
