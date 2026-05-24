<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\NoteFactoryInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: NotePage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von NotePage ab:
 *   - sichtbare Note mit korrektem Slug → 200 OK.
 *   - Note mit abweichendem Slug → 301 Moved Permanently (kanonische URL).
 *   - unbekannte Note-XREF → HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekt (Note) als Stub (Wert-orientiert),
 * Factory-Interfaces (NoteFactoryInterface, SlugFactoryInterface) sowie
 * Service-Mocks (ClipboardService, LinkedRecordService) als Mock mit
 * Erwartungs-Verifikation für die Pipeline der Linked-Record-Aufrufe.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\NotePage
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotePageTest.php
 */
class NotePageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('notepage', 'NotePage Test', self::DEMO_GED);
    }

    /**
     * Sichtbare Note rendert einen kanonischen Link-Header mit der Note-URL.
     *
     * Komplementaer zu test_handle_returns_ok_for_visible_note, das nur den
     * 200-Statuscode prueft. Hier wird die letzte Operation von handle() —
     * `->withHeader('Link', '<' . $record->url() . '>; rel="canonical"')` —
     * verhaltens-definitiv fixiert: der Link-Header wird mit dem RFC 5988
     * `rel="canonical"`-Marker auf die URL der Note gesetzt. Damit ist auch
     * dann erkennbar, wenn Upstream den Canonical-Mechanismus aenderte.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_sets_canonical_link_header_for_visible_note(): void
    {
        // Arrange
        $note_url = 'https://webtrees.test/note/N1';

        $note = self::createStub(Note::class);
        $note->method('xref')->willReturn('N1');
        $note->method('tree')->willReturn($this->tree);
        $note->method('canShow')->willReturn(true);
        $note->method('canEdit')->willReturn(false);
        $note->method('fullName')->willReturn('Test Note');
        $note->method('url')->willReturn($note_url);
        $note->method('facts')->willReturn(new Collection());

        $note_factory = $this->createMock(NoteFactoryInterface::class);
        $note_factory
            ->expects($this->once())
            ->method('make')
            ->with('N1', $this->tree)
            ->willReturn($note);
        Registry::noteFactory($note_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->once())
            ->method('pastableFacts')
            ->willReturn(new Collection());

        $linked_record_service = self::createStub(LinkedRecordService::class);
        $linked_record_service->method('linkedFamilies')->willReturn(new Collection());
        $linked_record_service->method('linkedIndividuals')->willReturn(new Collection());
        $linked_record_service->method('linkedLocations')->willReturn(new Collection());
        $linked_record_service->method('linkedMedia')->willReturn(new Collection());
        $linked_record_service->method('linkedRepositories')->willReturn(new Collection());
        $linked_record_service->method('linkedSources')->willReturn(new Collection());
        $linked_record_service->method('linkedSubmitters')->willReturn(new Collection());

        $handler = new NotePage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree, 'xref' => 'N1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: Link-Header ist gesetzt und enthaelt die Note-URL plus rel=canonical
        $link_header = $response->getHeaderLine('Link');
        self::assertNotSame('', $link_header, 'Link-Header muss bei 200-Render gesetzt sein');
        self::assertSame('<' . $note_url . '>; rel="canonical"', $link_header);
    }

    /**
     * Sichtbare Note mit übereinstimmendem Slug rendert die Note-Page mit 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_note(): void
    {
        // Arrange
        $note = self::createStub(Note::class);
        $note->method('xref')->willReturn('N1');
        $note->method('tree')->willReturn($this->tree);
        $note->method('canShow')->willReturn(true);
        $note->method('canEdit')->willReturn(false);
        $note->method('fullName')->willReturn('Test Note');
        $note->method('url')->willReturn('https://webtrees.test/note/N1');
        $note->method('facts')->willReturn(new Collection());

        $note_factory = $this->createMock(NoteFactoryInterface::class);
        $note_factory
            ->expects($this->once())
            ->method('make')
            ->with('N1', $this->tree)
            ->willReturn($note);
        Registry::noteFactory($note_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->once())
            ->method('pastableFacts')
            ->willReturn(new Collection());

        $linked_record_service = self::createStub(LinkedRecordService::class);
        $linked_record_service->method('linkedFamilies')->willReturn(new Collection());
        $linked_record_service->method('linkedIndividuals')->willReturn(new Collection());
        $linked_record_service->method('linkedLocations')->willReturn(new Collection());
        $linked_record_service->method('linkedMedia')->willReturn(new Collection());
        $linked_record_service->method('linkedRepositories')->willReturn(new Collection());
        $linked_record_service->method('linkedSources')->willReturn(new Collection());
        $linked_record_service->method('linkedSubmitters')->willReturn(new Collection());

        $handler = new NotePage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree, 'xref' => 'N1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL der Note.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $note = self::createStub(Note::class);
        $note->method('xref')->willReturn('N1');
        $note->method('tree')->willReturn($this->tree);
        $note->method('canShow')->willReturn(true);
        $note->method('canEdit')->willReturn(false);
        $note->method('url')->willReturn('https://webtrees.test/note/N1/test-note');

        $note_factory = $this->createMock(NoteFactoryInterface::class);
        $note_factory
            ->expects($this->once())
            ->method('make')
            ->with('N1', $this->tree)
            ->willReturn($note);
        Registry::noteFactory($note_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('test-note');
        Registry::slugFactory($slug_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new NotePage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree, 'xref' => 'N1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Note-XREF (Factory liefert null) löst HttpNotFoundException
     * aus Auth::checkNoteAccess() aus.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/NotePageTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_note_throws_not_found_exception(): void
    {
        // Arrange
        $note_factory = $this->createMock(NoteFactoryInterface::class);
        $note_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::noteFactory($note_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new NotePage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: ['tree' => $this->tree, 'xref' => 'X999', 'slug' => ''],
        );

        // Assert
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }
}
