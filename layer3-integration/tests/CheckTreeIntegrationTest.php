<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\RequestHandlers\CheckTree;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Komponentenintegrationstest: CheckTree RequestHandler mit MySQL.
 *
 * Testet Referenzintegrität-Prüfung auf valider demo.ged-Datenbasis.
 * CheckTree prüft: verwaiste Records, fehlende XREF-Links, inkonsistente
 * Beziehungen (6× DB::table() in handle()).
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CheckTree
 * @see docs/tds_conditions_ref.md G24
 */
class CheckTreeIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private CheckTree $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new CheckTree(
            new Gedcom(),
            new TimeoutService(new PhpService()),
        );
    }

    /**
     * G24 — CheckTree auf valider demo.ged: Handler gibt 200 OK zurück.
     */
    public function test_check_tree_returns_ok_for_valid_gedcom(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request  = $this->createRequest(attributes: ['tree' => $this->tree]);
        $response = $this->handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * G24 — CheckTree auf valider demo.ged: Ausgabe-Body nicht leer.
     */
    public function test_check_tree_produces_non_empty_body(): void
    {
        $this->createTreeWithGedcom('demo', 'Demo', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $request  = $this->createRequest(attributes: ['tree' => $this->tree]);
        $response = $this->handler->handle($request);

        $this->assertNotEmpty((string) $response->getBody());
    }

    /**
     * CheckTree auf leerem (frisch erstelltem) Baum liefert 200 OK.
     *
     * @group ported-l2-doubles
     */
    public function test_check_tree_returns_ok_for_empty_tree(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('check-empty', 'Check Empty');

        $timeout_service = $this->createTimeoutServiceMock(false);
        $handler         = new CheckTree(new Gedcom(), $timeout_service);

        // Act
        $request  = $this->createRequest(attributes: ['tree' => $this->tree]);
        $response = $handler->handle($request);

        // Assert
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CheckTree mit skip_to-Query-Parameter (Pagination-Resume) liefert 200 OK
     * auch wenn der angegebene XREF nicht existiert.
     *
     * @group ported-l2-doubles
     */
    public function test_check_tree_returns_ok_with_skip_to_parameter(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('check-skip', 'Check Skip');

        $timeout_service = $this->createTimeoutServiceMock(false);
        $handler         = new CheckTree(new Gedcom(), $timeout_service);

        // Act — skip_to verweist auf nicht existierenden XREF
        $request  = $this->createRequest(
            query: ['skip_to' => 'X999'],
            attributes: ['tree' => $this->tree],
        );
        $response = $handler->handle($request);

        // Assert
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CheckTree liefert 200 OK, wenn TimeoutService einen Timeout signalisiert
     * und der Handler in Pagination-Mode wechselt.
     *
     * @group ported-l2-doubles
     */
    public function test_check_tree_returns_ok_when_timeout_triggers_pagination(): void
    {
        // Arrange
        $this->createAndLoginAdmin();
        $this->tree = $this->treeService->create('check-timeout', 'Check Timeout');

        $timeout_service = $this->createTimeoutServiceMock(true);
        $handler         = new CheckTree(new Gedcom(), $timeout_service);

        // Act
        $request  = $this->createRequest(attributes: ['tree' => $this->tree]);
        $response = $handler->handle($request);

        // Assert
        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Helper: liefert einen TimeoutService-Mock mit konfiguriertem
     * isTimeNearlyUp()-Rückgabewert (Service → Mock per Stub/Mock-Konvention).
     *
     * @return TimeoutService&MockObject
     */
    private function createTimeoutServiceMock(bool $is_time_nearly_up): TimeoutService
    {
        $timeout_service = $this->createMock(TimeoutService::class);
        $timeout_service->method('isTimeNearlyUp')->willReturn($is_time_nearly_up);

        return $timeout_service;
    }
}
