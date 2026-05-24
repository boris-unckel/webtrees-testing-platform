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
 * @see docs/tds_conditions_ref.md E02
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CopyFact
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EmptyClipboard
 */
class CopyFactIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * Wenn die Fact-Collection mehrere Einträge enthält und der gesuchte Fact in der
     * Mitte liegt, ruft der Handler ClipboardService::copyFact() genau einmal mit
     * exakt diesem Fact auf und bricht die Schleife danach ab (kein Doppelaufruf
     * durch andere oder nachfolgende Facts mit gleicher ID).
     *
     * Ergänzt {@see self::test_handle_copies_matching_fact()} um die Loop-Short-Circuit-
     * Eigenschaft: das `break` nach erstem Treffer in CopyFact::handle() ist
     * verhaltensrelevant — ohne wird ein nachfolgender Fact mit gleicher ID
     * fälschlich erneut kopiert.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_copies_first_matching_fact_and_breaks(): void
    {
        // Arrange
        $this->createTreeWithGedcom('copy-fact-loop', 'CopyFact Loop', self::DEMO_GED);

        $fact_decoy_a = self::createStub(Fact::class);
        $fact_decoy_a->method('id')->willReturn('fact-other-a');

        $fact_target = self::createStub(Fact::class);
        $fact_target->method('id')->willReturn('fact-target');
        $fact_target->method('name')->willReturn('Death');

        // Zweiter Fact mit gleicher ID — würde ohne `break` ein zweites Mal kopiert.
        $fact_target_duplicate = self::createStub(Fact::class);
        $fact_target_duplicate->method('id')->willReturn('fact-target');
        $fact_target_duplicate->method('name')->willReturn('Death-Duplicate');

        $fact_decoy_b = self::createStub(Fact::class);
        $fact_decoy_b->method('id')->willReturn('fact-other-b');

        $record = self::createStub(GedcomRecord::class);
        $record->method('xref')->willReturn('X1');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('facts')->willReturn(new Collection([
            $fact_decoy_a,
            $fact_target,
            $fact_target_duplicate,
            $fact_decoy_b,
        ]));

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);

        Registry::gedcomRecordFactory($record_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->once())
            ->method('copyFact')
            ->with(self::identicalTo($fact_target));

        $handler = new CopyFact($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'X1', 'fact_id' => 'fact-target'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * Wenn fact_id genau einen Fact am Record trifft, ruft der Handler
     * ClipboardService::copyFact() genau einmal mit diesem Fact auf und
     * liefert 204 No Content.
     *
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
     * Security-Property: EmptyClipboard akzeptiert keine fremden Hosts als Redirect-Ziel.
     * Eine nicht-lokale URL im Body wird durch Validator::isLocalUrl() verworfen; der
     * Handler fällt auf den Referer-Header zurück (Open-Redirect-Schutz). Trotz
     * verworfener URL wird ClipboardService::emptyClipboard() unverändert aufgerufen.
     *
     * @group ported-l2-doubles
     */
    public function test_empty_clipboard_rejects_non_local_url_and_falls_back_to_referer(): void
    {
        // Arrange
        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects($this->once())
            ->method('emptyClipboard');

        $handler = new EmptyClipboard($clipboard_service);

        $referer = 'https://webtrees.test/index.php?route=/tree/x/manage';
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_POST,
            [],
            ['url' => 'https://evil.example.com/steal'],
        )->withHeader('Referer', $referer);

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame($referer, $response->getHeaderLine('Location'));
    }

    /**
     * EmptyClipboard ruft ClipboardService::emptyClipboard() genau einmal auf
     * und leitet danach auf die im Body übergebene lokale URL um (HTTP 302).
     *
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
