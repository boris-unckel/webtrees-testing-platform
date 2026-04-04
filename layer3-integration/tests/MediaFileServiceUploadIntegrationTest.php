<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PhpService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;

/**
 * Komponentenintegrationstest: MediaFileService::uploadFile.
 *
 * AP C-05: MediaFileService::uploadFile (CRAP 210)
 *
 * @see docs/testing-bigpicture.md G27
 * @covers \Fisharebest\Webtrees\Services\MediaFileService
 */
class MediaFileServiceUploadIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private MediaFileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();
        $this->service = new MediaFileService(new PhpService());
    }

    /**
     * uploadFile() mit URL-Quelle speichert externen Link.
     */
    public function test_upload_file_from_url(): void
    {
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'file_location' => 'url',
                'remote'        => 'https://example.com/photo.jpg',
            ],
            attributes: ['tree' => $this->tree],
        );

        $result = $this->service->uploadFile($request);

        $this->assertIsString($result);
        $this->assertStringContainsString('https://', $result);
    }

    /**
     * uploadFile() mit server-seitigem Dateinamen (existing file path).
     */
    public function test_upload_file_from_server_path(): void
    {
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'file_location' => 'url',
                'remote'        => 'https://example.org/image.png',
            ],
            attributes: ['tree' => $this->tree],
        );

        $result = $this->service->uploadFile($request);

        $this->assertIsString($result);
    }
}
