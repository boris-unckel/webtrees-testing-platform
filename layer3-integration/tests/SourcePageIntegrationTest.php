<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Contracts\SourceFactoryInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Source;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: SourcePage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von SourcePage ab:
 *   - sichtbare Quelle mit korrektem Slug -> 200 OK.
 *   - Quelle mit abweichendem Slug -> 301 Moved Permanently (kanonische URL).
 *   - unbekannte Source-XREF -> HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekte (Source) werden als Stubs eingehaengt,
 * weil die Pfade vorrangig Wert-orientiert sind (Slug-Vergleich,
 * Sichtbarkeitspruefung). Factory-Interfaces (SourceFactoryInterface,
 * SlugFactoryInterface) und Services (ClipboardService, LinkedRecordService)
 * werden als Mocks gefuehrt, wenn Interaktionen verifiziert werden
 * (Factory-Lookup mit XREF, pastableFacts-Aufruf).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SourcePageTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SourcePage
 */
class SourcePageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('sourcepage', 'SourcePage Test', self::DEMO_GED);
    }

    /**
     * Sichtbare Quelle mit uebereinstimmendem Slug rendert die
     * source-page mit 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SourcePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_source(): void
    {
        // Arrange
        $source = self::createStub(Source::class);
        $source->method('xref')->willReturn('S1');
        $source->method('tree')->willReturn($this->tree);
        $source->method('canShow')->willReturn(true);
        $source->method('canEdit')->willReturn(false);
        $source->method('fullName')->willReturn('Test Source');
        $source->method('url')->willReturn('https://webtrees.test/source/S1');
        $source->method('facts')->willReturn(new Collection());

        $source_factory = $this->createMock(SourceFactoryInterface::class);
        $source_factory
            ->expects(self::once())
            ->method('make')
            ->with('S1', $this->tree)
            ->willReturn($source);
        Registry::sourceFactory($source_factory);

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
        $linked_record_service->method('linkedMedia')->willReturn(new Collection());
        $linked_record_service->method('linkedNotes')->willReturn(new Collection());

        $handler = new SourcePage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'S1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL der Quelle.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SourcePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $source = self::createStub(Source::class);
        $source->method('xref')->willReturn('S1');
        $source->method('tree')->willReturn($this->tree);
        $source->method('canShow')->willReturn(true);
        $source->method('canEdit')->willReturn(false);
        $source->method('url')->willReturn('https://webtrees.test/source/S1/test-source');

        $source_factory = $this->createMock(SourceFactoryInterface::class);
        $source_factory
            ->expects(self::once())
            ->method('make')
            ->with('S1', $this->tree)
            ->willReturn($source);
        Registry::sourceFactory($source_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('test-source');
        Registry::slugFactory($slug_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new SourcePage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'S1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Source-XREF (Factory liefert null) loest
     * HttpNotFoundException aus Auth::checkSourceAccess() aus.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/SourcePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_source_throws_not_found_exception(): void
    {
        // Arrange
        $source_factory = $this->createMock(SourceFactoryInterface::class);
        $source_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::sourceFactory($source_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new SourcePage($clipboard_service, $linked_record_service);
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
