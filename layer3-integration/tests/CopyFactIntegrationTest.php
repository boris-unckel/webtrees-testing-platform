<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\GedcomRecordFactoryInterface;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\CopyFact;
use Fisharebest\Webtrees\Http\RequestHandlers\EmptyClipboard;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: CopyFact HTTP-Handler.
 *
 * Verifiziert das Kopieren einer Tatsache (Fact) in den Clipboard-Service:
 * - Matching Fact → ClipboardService::copyFact() wird aufgerufen.
 * - Nicht-matching Fact-ID → kein Aufruf am ClipboardService.
 * - Unbekannter Record → HttpNotFoundException aus Auth::checkRecordAccess().
 *
 * Erweitert um EmptyClipboard-Handler (gleicher Clipboard-Service-Kontext):
 * - emptyClipboard() wird aufgerufen und Handler leitet weiter (302).
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CopyFactTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EmptyClipboardTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CopyFact
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EmptyClipboard
 */
class CopyFactIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * Bootstrap-Smoke: Handler-Klasse ist autoloadbar.
     *
     * @group ported-l2-doubles
     */
    public function test_class_exists(): void
    {
        self::assertTrue(class_exists(CopyFact::class));
    }

    /**
     * Wenn fact_id genau einen Fact am Record trifft, ruft der Handler
     * ClipboardService::copyFact() genau einmal mit diesem Fact auf und
     * liefert 204 No Content.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CopyFactTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_copies_matching_fact(): void
    {
        // Arrange
        $this->createTreeWithGedcom('copy-fact-match', 'CopyFact Match', self::DEMO_GED);

        $fact = self::createStub(Fact::class);
        $fact->method('id')->willReturn('fact-123');
        $fact->method('name')->willReturn('Birth');

        $record = self::createStub(GedcomRecord::class);
        $record->method('xref')->willReturn('X1');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('facts')->willReturn(new Collection([$fact]));

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);

        Registry::gedcomRecordFactory($record_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->once())
            ->method('copyFact')
            ->with($fact);

        $handler = new CopyFact($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'X1', 'fact_id' => 'fact-123'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Wenn keine Fact-ID den am Record vorhandenen Facts entspricht, wird
     * ClipboardService::copyFact() nicht aufgerufen; Handler liefert dennoch
     * 204 No Content (keine Fehlermeldung).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CopyFactTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_no_matching_fact(): void
    {
        // Arrange
        $this->createTreeWithGedcom('copy-fact-miss', 'CopyFact Miss', self::DEMO_GED);

        $fact = self::createStub(Fact::class);
        $fact->method('id')->willReturn('fact-other');

        $record = self::createStub(GedcomRecord::class);
        $record->method('xref')->willReturn('X1');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('facts')->willReturn(new Collection([$fact]));

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);

        Registry::gedcomRecordFactory($record_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->never())
            ->method('copyFact');

        $handler = new CopyFact($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'X1', 'fact_id' => 'nonexistent'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Wenn die GedcomRecordFactory zum Xref keinen Record liefert, wirft
     * Auth::checkRecordAccess() eine HttpNotFoundException — der Handler
     * darf die Exception nicht maskieren.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CopyFactTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_record_throws_not_found_exception(): void
    {
        // Arrange
        $this->createTreeWithGedcom('copy-fact-unknown', 'CopyFact Unknown', self::DEMO_GED);

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn(null);

        Registry::gedcomRecordFactory($record_factory);

        $clipboard_service = self::createStub(ClipboardService::class);

        $handler = new CopyFact($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'X999', 'fact_id' => 'fact-123'],
        );

        // Assert (Exception-Erwartung vor Act)
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * Bootstrap-Smoke: EmptyClipboard-Handler ist autoloadbar.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EmptyClipboardTest.php
     * @group ported-l2-doubles
     */
    public function test_empty_clipboard_class_exists(): void
    {
        self::assertTrue(class_exists(EmptyClipboard::class));
    }

    /**
     * EmptyClipboard ruft ClipboardService::emptyClipboard() genau einmal auf
     * und leitet danach auf die im Body übergebene lokale URL um (HTTP 302).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EmptyClipboardTest.php
     * @group ported-l2-doubles
     */
    public function test_empty_clipboard_handle_empties_and_redirects(): void
    {
        // Arrange
        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->once())
            ->method('emptyClipboard');

        $handler = new EmptyClipboard($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            ['url' => 'https://webtrees.test/index.php'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }
}
