<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\CleanDataFolder;
use Fisharebest\Webtrees\Http\RequestHandlers\DataFixChoose;
use Fisharebest\Webtrees\Http\RequestHandlers\DataFixPage;
use Fisharebest\Webtrees\Http\RequestHandlers\DeletePath;
use Fisharebest\Webtrees\Http\RequestHandlers\FindDuplicateRecords;
use Fisharebest\Webtrees\Services\AdminService;
use Fisharebest\Webtrees\Services\ModuleService;
use League\Flysystem\WhitespacePathNormalizer;

/**
 * Komponentenintegrationstest: Datenpflege-Werkzeuge — A09.
 *
 * Tests:
 * - FindDuplicateRecords GET → 200
 * - DataFixPage GET: kein spezifisches Modul → 200 (Auswahl-View)
 * - DataFixPage GET: spezifisches Modul → 200 (DataFix-Seite)
 * - DataFixChoose GET → 200
 * - CleanDataFolder GET → 200 (Data-Folder-Aufräumseite)
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\FindDuplicateRecords
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DataFixPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DataFixChoose
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CleanDataFolder
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeletePath
 * @see docs/testquality_improve_A09.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/DeletePathTest.php
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

    /**
     * EP5: CleanDataFolder GET → 200 (Auflistung Daten-Ordner-Inhalt + geschützte Pfade).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CleanDataFolderTest.php
     * @group ported-l2-doubles
     */
    public function test_clean_data_folder_returns_200(): void
    {
        // Arrange
        $handler = new CleanDataFolder($this->treeService);
        $request = $this->createRequest();

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP6: DeletePath verweigert das Löschen geschützter Datei `config.ini.php` → 204.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/DeletePathTest.php
     * @group ported-l2-doubles
     */
    public function test_delete_path_refuses_to_delete_protected_config_ini(): void
    {
        // Arrange
        $handler = new DeletePath(new WhitespacePathNormalizer());
        $request = $this->createRequest(query: ['path' => 'config.ini.php']);

        // Act
        $response = $handler->handle($request);

        // Assert: protected files cannot be deleted; handler returns empty response (204)
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP7: DeletePath verweigert das Löschen geschützter Datei `.htaccess` → 204.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/DeletePathTest.php
     * @group ported-l2-doubles
     */
    public function test_delete_path_refuses_to_delete_protected_htaccess(): void
    {
        // Arrange
        $handler = new DeletePath(new WhitespacePathNormalizer());
        $request = $this->createRequest(query: ['path' => '.htaccess']);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP8: DeletePath verweigert das Löschen geschützter Datei `index.php` → 204.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/DeletePathTest.php
     * @group ported-l2-doubles
     */
    public function test_delete_path_refuses_to_delete_protected_index_php(): void
    {
        // Arrange
        $handler = new DeletePath(new WhitespacePathNormalizer());
        $request = $this->createRequest(query: ['path' => 'index.php']);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }
}
