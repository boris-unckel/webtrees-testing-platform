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
use Fisharebest\Webtrees\Module\SiteMapModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\ServerCheckService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
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

    /**
     * EP11: AppAdsTxt-Body enthält den "#No pesky ads here"-Marker.
     *
     * Status und Content-Type werden bereits durch den utilityHandlerProvider abgedeckt;
     * diese Methode ergänzt den Body-Inhalt-Check aus der portierten Quelle.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AppAdsTxtTest.php
     * @group ported-l2-doubles
     */
    public function test_app_ads_txt_body_contains_no_pesky_ads_marker(): void
    {
        $handler  = new AppAdsTxt();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('content-type'));

        $body = (string) $response->getBody();
        self::assertStringContainsString('#No pesky ads here', $body);
    }

    /**
     * EP12: AppleTouchIconPng setzt langfristigen Cache-Control-Header und liefert nicht-leeren PNG-Body.
     *
     * Status und Content-Type werden bereits durch den utilityHandlerProvider abgedeckt;
     * diese Methode ergänzt Cache-Control- und Body-Checks aus der portierten Quelle.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AppleTouchIconPngTest.php
     * @group ported-l2-doubles
     */
    public function test_apple_touch_icon_png_cache_control_and_body(): void
    {
        $handler  = new AppleTouchIconPng();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('content-type'));
        self::assertSame('public,max-age=31536000', $response->getHeaderLine('cache-control'));

        $body = (string) $response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * EP13: BrowserconfigXml setzt langfristigen Cache-Control-Header und liefert nicht-leeren XML-Body.
     *
     * Status und Content-Type werden bereits durch den utilityHandlerProvider abgedeckt;
     * diese Methode ergänzt Cache-Control- und Body-Checks aus der portierten Quelle.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/BrowserconfigXmlTest.php
     * @group ported-l2-doubles
     */
    public function test_browserconfig_xml_cache_control_and_body(): void
    {
        $handler  = new BrowserconfigXml();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('application/xml', $response->getHeaderLine('content-type'));
        self::assertSame('public,max-age=31536000', $response->getHeaderLine('cache-control'));

        $body = (string) $response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * EP14: FaviconIco setzt langfristigen Cache-Control-Header und liefert nicht-leeren Icon-Body.
     *
     * Status und Content-Type werden bereits durch den utilityHandlerProvider abgedeckt;
     * diese Methode ergänzt Cache-Control- und Body-Checks aus der portierten Quelle.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/FaviconIcoTest.php
     * @group ported-l2-doubles
     */
    public function test_favicon_ico_cache_control_and_body(): void
    {
        $handler  = new FaviconIco();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('image/x-icon', $response->getHeaderLine('content-type'));
        self::assertSame('public,max-age=31536000', $response->getHeaderLine('cache-control'));

        $body = (string) $response->getBody();
        self::assertNotEmpty($body);
    }

    /**
     * EP15: RobotsTxt liefert auch ohne registrierte Trees einen 200-Response.
     *
     * Sicherstellung, dass leere Tree-Listen nicht zu Fehlern führen
     * (Edge-Case-Abdeckung aus der portierten Quelle).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RobotsTxtTest.php
     * @group ported-l2-doubles
     */
    public function test_robots_txt_handles_empty_tree_list(): void
    {
        // Arrange.
        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection());

        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(SiteMapModule::class)
            ->willReturn(new Collection());

        $handler = new RobotsTxt($module_service, $tree_service);

        // Act.
        $request  = $this->createRequest(attributes: ['base_url' => 'https://webtrees.test']);
        $response = $handler->handle($request);

        // Assert.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', $response->getHeaderLine('content-type'));
    }

    /**
     * EP16: RobotsTxt liefert bei mehreren Trees einen 200-Response mit text/plain.
     *
     * Sicherstellung, dass mehrere Tree-Einträge iteriert werden, ohne Fehler auszulösen
     * (Mehrfach-Tree-Abdeckung aus der portierten Quelle).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/RobotsTxtTest.php
     * @group ported-l2-doubles
     */
    public function test_robots_txt_handles_multiple_trees(): void
    {
        // Arrange.
        $tree1 = self::createStub(Tree::class);
        $tree1->method('name')->willReturn('tree1');
        $tree2 = self::createStub(Tree::class);
        $tree2->method('name')->willReturn('tree2');

        $tree_service = $this->createMock(TreeService::class);
        $tree_service->expects(self::once())
            ->method('all')
            ->willReturn(new Collection([$tree1, $tree2]));

        $module_service = $this->createMock(ModuleService::class);
        $module_service->expects(self::once())
            ->method('findByInterface')
            ->with(SiteMapModule::class)
            ->willReturn(new Collection());

        $handler = new RobotsTxt($module_service, $tree_service);

        // Act.
        $request  = $this->createRequest(attributes: ['base_url' => 'https://webtrees.test']);
        $response = $handler->handle($request);

        // Assert.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', $response->getHeaderLine('content-type'));
    }

    /**
     * EP17: WebmanifestJson setzt langfristigen Cache-Control-Header und liefert nicht-leeren JSON-Body.
     *
     * Status und Content-Type werden bereits durch den utilityHandlerProvider abgedeckt;
     * diese Methode ergänzt Cache-Control- und Body-Checks aus der portierten Quelle.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/WebmanifestJsonTest.php
     * @group ported-l2-doubles
     */
    public function test_webmanifest_json_cache_control_and_body(): void
    {
        $handler  = new WebmanifestJson();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame('public,max-age=31536000', $response->getHeaderLine('cache-control'));

        $body = (string) $response->getBody();
        self::assertNotEmpty($body);
    }
}
