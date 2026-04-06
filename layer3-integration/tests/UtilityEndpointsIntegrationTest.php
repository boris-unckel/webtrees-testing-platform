<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\AdsTxt;
use Fisharebest\Webtrees\Http\RequestHandlers\AppAdsTxt;
use Fisharebest\Webtrees\Http\RequestHandlers\AppleTouchIconPng;
use Fisharebest\Webtrees\Http\RequestHandlers\BrowserconfigXml;
use Fisharebest\Webtrees\Http\RequestHandlers\FaviconIco;
use Fisharebest\Webtrees\Http\RequestHandlers\Ping;
use Fisharebest\Webtrees\Http\RequestHandlers\RobotsTxt;
use Fisharebest\Webtrees\Http\RequestHandlers\WebmanifestJson;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\ServerCheckService;
use Fisharebest\Webtrees\Services\TreeService;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Komponentenintegrationstest: Web-Assets & Utility-Endpoints (SEC-UTL01).
 *
 * DataProvider-Batch für alle 8 Utility-Handler. Kein Auth nötig.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RobotsTxt
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\FaviconIco
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\WebmanifestJson
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\BrowserconfigXml
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AppleTouchIconPng
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AdsTxt
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AppAdsTxt
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\Ping
 * @see docs/testquality_improve_SEC-UTL01.md
 */
class UtilityEndpointsIntegrationTest extends MysqlTestCase
{
    /**
     * DataProvider: Handler-Klasse → erwarteter Content-Type-Prefix.
     *
     * @return array<string, array{RequestHandlerInterface, string}>
     */
    public static function utilityHandlerProvider(): array
    {
        return [
            'favicon'       => [new FaviconIco(), 'image/x-icon'],
            'webmanifest'   => [new WebmanifestJson(), 'application/json'],
            'browserconfig' => [new BrowserconfigXml(), 'application/xml'],
            'apple-touch'   => [new AppleTouchIconPng(), 'image/png'],
            'ads'           => [new AdsTxt(), 'text/plain'],
            'app-ads'       => [new AppAdsTxt(), 'text/plain'],
        ];
    }

    /**
     * EP1–EP6: Einfache Asset-Handler geben 200 + korrekten Content-Type zurück.
     */
    #[DataProvider('utilityHandlerProvider')]
    public function test_utility_handler_returns_200_with_correct_content_type(
        RequestHandlerInterface $handler,
        string $expected_content_type,
    ): void {
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith($expected_content_type, $response->getHeaderLine('content-type'));
    }

    /**
     * EP7: RobotsTxt → 200, text/plain, enthält "User-agent: *".
     */
    public function test_robots_txt_returns_200_plain_with_user_agent(): void
    {
        $handler = new RobotsTxt(
            new ModuleService(),
            $this->treeService,
        );

        $request  = $this->createRequest(attributes: ['base_url' => 'https://webtrees.test']);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', $response->getHeaderLine('content-type'));
        self::assertStringContainsString('User-agent:', (string) $response->getBody());
    }

    /**
     * EP8: RobotsTxt enthält "Disallow:" für geschützte Pfade.
     */
    public function test_robots_txt_contains_disallow_entries(): void
    {
        $handler = new RobotsTxt(
            new ModuleService(),
            $this->treeService,
        );

        $request  = $this->createRequest(attributes: ['base_url' => 'https://webtrees.test']);
        $response = $handler->handle($request);

        $body = (string) $response->getBody();
        self::assertStringContainsString('Disallow:', $body);
    }

    /**
     * EP9: Ping → 200 (oder 503 wenn Server-Fehler).
     */
    public function test_ping_returns_ok_or_unavailable(): void
    {
        $handler = new Ping(
            Registry::container()->get(ServerCheckService::class),
        );

        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertContains(
            $response->getStatusCode(),
            [StatusCodeInterface::STATUS_OK, StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE],
        );
    }

    /**
     * EP10: Ping gibt "OK" oder "WARNING" oder "ERROR" als Body zurück.
     */
    public function test_ping_body_is_ok_warning_or_error(): void
    {
        $handler = new Ping(
            Registry::container()->get(ServerCheckService::class),
        );

        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertContains((string) $response->getBody(), ['OK', 'WARNING', 'ERROR']);
    }
}
