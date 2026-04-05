<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageMediaData;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\DatatablesService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TreeService;

/**
 * Komponentenintegrationstest: ManageMediaData.
 *
 * AP C-01: ManageMediaData::handle (CRAP 272), ManageMediaData::mediaObjectInfo (CRAP 110)
 *
 * @see docs/testing-bigpicture.md S49
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ManageMediaData
 */
class ManageMediaDataIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private ManageMediaData $handler;

    private MediaFileService $mediaFileService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $this->mediaFileService = new MediaFileService(new PhpService());
        $this->handler = new ManageMediaData(
            datatables_service:    new DatatablesService(),
            linked_record_service: new LinkedRecordService(),
            media_file_service:    $this->mediaFileService,
            tree_service:          new TreeService($this->gedcomImportService),
        );
    }

    /**
     * handle() gibt Datatable-JSON für lokale Mediendateien zurück.
     */
    public function test_handle_returns_datatable_json_for_local_files(): void
    {
        $dataFs      = Registry::filesystem()->data();
        $validFolder = $this->mediaFileService->allMediaFolders($dataFs)->first() ?? '';

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query:  [
                'files'        => 'local',
                'media_folder' => $validFolder,
                'subfolders'   => 'include',
                'filter'       => '',
                'draw'         => '1',
                'start'        => '0',
                'length'       => '10',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }

    /**
     * handle() gibt Datatable-JSON für externe Mediendateien zurück (EP2).
     */
    public function test_handle_returns_datatable_json_for_external_files(): void
    {
        $dataFs      = Registry::filesystem()->data();
        $validFolder = $this->mediaFileService->allMediaFolders($dataFs)->first() ?? '';

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query:  [
                'files'        => 'external',
                'media_folder' => $validFolder,
                'subfolders'   => 'include',
                'filter'       => '',
                'draw'         => '1',
                'start'        => '0',
                'length'       => '10',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }

    /**
     * handle() gibt Datatable-JSON für nicht referenzierte Dateien zurück (EP3 — unused-Branch).
     * Nutzt handleCollection statt handleQuery — separater Code-Pfad.
     */
    public function test_handle_returns_datatable_json_for_unused_files(): void
    {
        $dataFs      = Registry::filesystem()->data();
        $validFolder = $this->mediaFileService->allMediaFolders($dataFs)->first() ?? '';

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            query:  [
                'files'        => 'unused',
                'media_folder' => $validFolder,
                'subfolders'   => 'include',
                'filter'       => '',
                'draw'         => '1',
                'start'        => '0',
                'length'       => '10',
            ],
            attributes: ['tree' => $this->tree],
        );

        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
        $this->assertArrayHasKey('recordsFiltered', $json);
    }
}
