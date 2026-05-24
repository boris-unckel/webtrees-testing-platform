<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\GedcomRecordFactoryInterface;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\SearchQuickAction;
use Fisharebest\Webtrees\Registry;

/**
 * Komponentenintegrationstest: SearchQuickAction HTTP-Handler.
 *
 * Der Handler liest die Suchanfrage aus dem POST-Body, schlaegt das XREF in der
 * GedcomRecordFactory nach und leitet entweder direkt auf den gefundenen Record
 * weiter oder, falls keiner gefunden wird, auf die allgemeine Suchseite. Die
 * Aufloesung des Records wird gegen einen gemockten GedcomRecordFactory
 * gefuehrt, der Baum selbst stammt aus einer echten MySQL-DB-Tree-Instanz.
 *
 * @see docs/tds_conditions_ref.md S09
 * @group ported-l2-doubles
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SearchQuickAction
 */
class SearchQuickActionIntegrationTest extends MysqlTestCase
{
    /**
     * SearchQuickAction leitet auf den Record-URL um, wenn die Factory einen
     * sichtbaren Record liefert (canShow=true). Die Antwort traegt HTTP 302
     * und den Location-Header mit der Record-URL.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_to_record_when_found(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-quick-' . substr(md5($this->name()), 0, 8), 'Test');

        $record = self::createStub(GedcomRecord::class);
        $record->method('canShow')->willReturn(true);
        $record->method('url')->willReturn('https://webtrees.test/tree/test/individual/I1');

        $factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $factory
            ->expects($this->once())
            ->method('make')
            ->with('I1', $this->tree)
            ->willReturn($record);

        Registry::gedcomRecordFactory($factory);

        $handler = new SearchQuickAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['query' => 'I1'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertSame('https://webtrees.test/tree/test/individual/I1', $response->getHeaderLine('Location'));
    }

    /**
     * Findet die Factory keinen Record fuer das gesuchte XREF, leitet der
     * Handler auf die allgemeine Suchseite weiter und reicht Baum-Name sowie
     * Suchanfrage in die Folge-URL durch.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_to_search_page_when_not_found(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('search-quick-nf-' . substr(md5($this->name()), 0, 8), 'Test 2');

        $factory = $this->createMock(GedcomRecordFactoryInterface::class);
        $factory
            ->expects($this->once())
            ->method('make')
            ->with('NOTFOUND', $this->tree)
            ->willReturn(null);

        Registry::gedcomRecordFactory($factory);

        $handler = new SearchQuickAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['query' => 'NOTFOUND'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
        self::assertStringContainsString('NOTFOUND', $response->getHeaderLine('Location'));
        self::assertStringContainsString($this->tree->name(), $response->getHeaderLine('Location'));
    }
}
