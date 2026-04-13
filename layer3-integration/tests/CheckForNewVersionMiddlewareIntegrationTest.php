<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Http\Middleware\CheckForNewVersion;
use Fisharebest\Webtrees\Services\UpgradeService;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: CheckForNewVersion-Middleware (M23).
 *
 * Prüft die Update-Prüfung: GET ohne XHR → Check, GET mit XHR → Skip, POST → Skip.
 *
 * @see docs/tds_conditions_ref.md M23
 * @covers \Fisharebest\Webtrees\Http\Middleware\CheckForNewVersion
 */
class CheckForNewVersionMiddlewareIntegrationTest extends MysqlTestCase
{
    /**
     * EP1/B1: GET ohne X-Requested-With → isUpgradeAvailable() wird aufgerufen.
     */
    public function test_get_without_xhr_checks_upgrade(): void
    {
        $upgradeService = $this->createMock(UpgradeService::class);
        $upgradeService->expects($this->once())->method('isUpgradeAvailable');

        $middleware = new CheckForNewVersion($upgradeService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP2/B2: GET mit X-Requested-With: XMLHttpRequest → kein Upgrade-Check.
     */
    public function test_get_with_xhr_skips_upgrade_check(): void
    {
        $upgradeService = $this->createMock(UpgradeService::class);
        $upgradeService->expects($this->never())->method('isUpgradeAvailable');

        $middleware = new CheckForNewVersion($upgradeService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $request  = $this->createRequest()
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * EP3/B3: POST-Request → kein Upgrade-Check.
     */
    public function test_post_request_skips_upgrade_check(): void
    {
        $upgradeService = $this->createMock(UpgradeService::class);
        $upgradeService->expects($this->never())->method('isUpgradeAvailable');

        $middleware = new CheckForNewVersion($upgradeService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('OK'));

        $request  = $this->createRequest(method: RequestMethodInterface::METHOD_POST);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Handler-Response wird unverändert zurückgegeben.
     */
    public function test_handler_response_returned_unchanged(): void
    {
        $upgradeService = $this->createStub(UpgradeService::class);
        $middleware      = new CheckForNewVersion($upgradeService);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(response('Expected-Body'));

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame('Expected-Body', (string) $response->getBody());
    }
}
