<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\MediaFactoryInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: MediaPage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von MediaPage ab:
 *   - sichtbares Medium mit korrektem Slug -> 200 OK.
 *   - Medium mit abweichendem Slug -> 301 Moved Permanently (kanonische URL).
 *   - unbekannte Media-XREF -> HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekte (Media) werden als Stubs eingehaengt,
 * weil die Pfade vorrangig Wert-orientiert sind (Slug-Vergleich,
 * Sichtbarkeitspruefung). Factory-Interfaces (MediaFactoryInterface,
 * SlugFactoryInterface) und Services (ClipboardService, LinkedRecordService)
 * werden als Mocks gefuehrt, weil Interaktionen verifiziert werden
 * (Factory-Lookup mit XREF, pastableFacts-Aufruf).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MediaPageTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MediaPage
 */
class MediaPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('mediapage', 'MediaPage Test', self::DEMO_GED);
    }

    /**
     * Sichtbares Medium mit uebereinstimmendem Slug rendert die
     * media-page mit 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MediaPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_media(): void
    {
        // Arrange
        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(true);
        $media->method('canEdit')->willReturn(false);
        $media->method('fullName')->willReturn('Test Media');
        $media->method('url')->willReturn('https://webtrees.test/media/M1');
        $media->method('facts')->willReturn(new Collection());
        $media->method('mediaFiles')->willReturn(new Collection());

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);
        Registry::mediaFactory($media_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects(self::once())
            ->method('pastableFacts')
            ->willReturn(new Collection());

        $linked_record_service = $this->createMock(LinkedRecordService::class);
        $linked_record_service->method('linkedFamilies')->willReturn(new Collection());
        $linked_record_service->method('linkedIndividuals')->willReturn(new Collection());
        $linked_record_service->method('linkedLocations')->willReturn(new Collection());
        $linked_record_service->method('linkedNotes')->willReturn(new Collection());
        $linked_record_service->method('linkedSources')->willReturn(new Collection());

        $handler = new MediaPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'M1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL des Mediums.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MediaPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(true);
        $media->method('canEdit')->willReturn(false);
        $media->method('url')->willReturn('https://webtrees.test/media/M1/test-media');

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);
        Registry::mediaFactory($media_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('test-media');
        Registry::slugFactory($slug_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new MediaPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'M1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Media-XREF (Factory liefert null) loest
     * HttpNotFoundException aus Auth::checkMediaAccess() aus.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MediaPageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_media_throws_not_found_exception(): void
    {
        // Arrange
        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::mediaFactory($media_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new MediaPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'X999', 'slug' => ''],
        );

        // Assert
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }
}
