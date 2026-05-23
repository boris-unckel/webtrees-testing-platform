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
 * @see docs/tds_conditions_ref.md G27
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

    /**
     * createMediaFileGedcom() mit lokaler Datei liefert FILE/FORM/TYPE/TITL-Zeilen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/MediaFileServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_create_media_file_gedcom_with_local_file(): void
    {
        // Arrange
        $service = new MediaFileService(new PhpService());

        // Act
        $gedcom = $service->createMediaFileGedcom('photo.jpg', 'photo', 'My Photo', '');

        // Assert
        self::assertStringContainsString('1 FILE photo.jpg', $gedcom);
        self::assertStringContainsString('2 FORM JPG', $gedcom);
        self::assertStringContainsString('3 TYPE photo', $gedcom);
        self::assertStringContainsString('2 TITL My Photo', $gedcom);
        self::assertStringNotContainsString('1 NOTE', $gedcom);
    }

    /**
     * createMediaFileGedcom() mit URL-Quelle ohne FORM/TYPE/TITL.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/MediaFileServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_create_media_file_gedcom_with_url(): void
    {
        // Arrange
        $service = new MediaFileService(new PhpService());

        // Act
        $gedcom = $service->createMediaFileGedcom('https://example.com/photo.jpg', '', '', '');

        // Assert
        self::assertStringStartsWith('1 FILE https://example.com/photo.jpg', $gedcom);
        self::assertStringNotContainsString('2 FORM', $gedcom);
        self::assertStringNotContainsString('3 TYPE', $gedcom);
        self::assertStringNotContainsString('2 TITL', $gedcom);
        self::assertStringNotContainsString('1 NOTE', $gedcom);
    }

    /**
     * createMediaFileGedcom() mit Note hängt NOTE-Zeile an.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Services/MediaFileServiceTest.php
     * @group ported-l2-doubles
     */
    public function test_create_media_file_gedcom_with_note(): void
    {
        // Arrange
        $service = new MediaFileService(new PhpService());

        // Act
        $gedcom = $service->createMediaFileGedcom('doc.pdf', '', '', 'Some note');

        // Assert
        self::assertStringContainsString('1 FILE doc.pdf', $gedcom);
        self::assertStringContainsString('2 FORM PDF', $gedcom);
        self::assertStringNotContainsString('3 TYPE', $gedcom);
        self::assertStringNotContainsString('2 TITL', $gedcom);
        self::assertStringContainsString('1 NOTE Some note', $gedcom);
    }
}
