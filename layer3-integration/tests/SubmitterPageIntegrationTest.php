<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Contracts\SubmitterFactoryInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Submitter;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: SubmitterPage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von SubmitterPage ab:
 *   - sichtbarer Submitter mit korrektem Slug -> 200 OK.
 *   - Submitter mit abweichendem Slug -> 301 Moved Permanently (kanonische URL).
 *   - unbekannte Submitter-XREF -> HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekte (Submitter) werden als Stubs eingehaengt;
 * Factory-Interfaces (SubmitterFactoryInterface, SlugFactoryInterface) und
 * Services (ClipboardService, LinkedRecordService) werden als Mocks gefuehrt,
 * wenn Verhalten (Aufrufzahlen, Argumente) verifiziert wird.
 *
 * @see docs/tds_conditions_ref.md S30
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage
 */
class SubmitterPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('subpage', 'SubmitterPage Test', self::DEMO_GED);
    }

    /**
     * Sichtbarer Submitter mit uebereinstimmendem Slug rendert die
     * submitter-page mit 200.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_submitter(): void
    {
        // Arrange
        $submitter = self::createStub(Submitter::class);
        $submitter->method('xref')->willReturn('U1');
        $submitter->method('tree')->willReturn($this->tree);
        $submitter->method('canShow')->willReturn(true);
        $submitter->method('canEdit')->willReturn(false);
        $submitter->method('fullName')->willReturn('Test Submitter');
        $submitter->method('url')->willReturn('https://webtrees.test/submitter/U1');
        $submitter->method('facts')->willReturn(new Collection());

        $submitter_factory = $this->createMock(SubmitterFactoryInterface::class);
        $submitter_factory
            ->expects(self::once())
            ->method('make')
            ->with('U1', $this->tree)
            ->willReturn($submitter);
        Registry::submitterFactory($submitter_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
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

        $handler = new SubmitterPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'U1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL des Submitters.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $submitter = self::createStub(Submitter::class);
        $submitter->method('xref')->willReturn('U1');
        $submitter->method('tree')->willReturn($this->tree);
        $submitter->method('canShow')->willReturn(true);
        $submitter->method('canEdit')->willReturn(false);
        $submitter->method('url')->willReturn('https://webtrees.test/submitter/U1/test-submitter');

        $submitter_factory = $this->createMock(SubmitterFactoryInterface::class);
        $submitter_factory
            ->expects(self::once())
            ->method('make')
            ->with('U1', $this->tree)
            ->willReturn($submitter);
        Registry::submitterFactory($submitter_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('test-submitter');
        Registry::slugFactory($slug_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new SubmitterPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'U1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Submitter-XREF (Factory liefert null) loest
     * HttpNotFoundException aus Auth::checkSubmitterAccess() aus.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_submitter_throws_not_found_exception(): void
    {
        // Arrange
        $submitter_factory = $this->createMock(SubmitterFactoryInterface::class);
        $submitter_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::submitterFactory($submitter_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new SubmitterPage($clipboard_service, $linked_record_service);
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
