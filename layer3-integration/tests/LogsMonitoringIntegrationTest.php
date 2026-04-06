<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogPage;
use Fisharebest\Webtrees\Http\RequestHandlers\PhpInformation;
use Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsDownload;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\SiteLogsService;

/**
 * Komponentenintegrationstest: Protokolle & Monitoring — A10.
 *
 * Tests:
 * - PendingChangesLogPage GET → 200
 * - SiteLogsDownload GET → 200 + text/csv
 * - PhpInformation GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PendingChangesLogPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SiteLogsDownload
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\PhpInformation
 * @see docs/testquality_improve_A10.md
 */
class LogsMonitoringIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('a10-logs', 'A10 Logs', self::DEMO_GED);
    }

    /**
     * EP1: PendingChangesLogPage GET → 200.
     */
    public function test_pending_changes_log_page_returns_200(): void
    {
        $handler = new PendingChangesLogPage(
            $this->treeService,
            $this->userService,
        );

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: SiteLogsDownload GET → 200 + text/csv Content-Type.
     */
    public function test_site_logs_download_returns_csv(): void
    {
        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );

        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => '',
                'text'     => '',
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringStartsWith('text/csv', $response->getHeaderLine('content-type'));
    }

    /**
     * EP3: SiteLogsDownload GET → Content-Disposition ist attachment mit Dateinamen.
     */
    public function test_site_logs_download_has_attachment_disposition(): void
    {
        $handler = new SiteLogsDownload(
            Registry::container()->get(SiteLogsService::class),
        );

        $request = $this->createRequest(
            query: [
                'from'     => '2020-01-01',
                'to'       => '2030-12-31',
                'type'     => '',
                'text'     => '',
                'ip'       => '',
                'username' => '',
                'tree'     => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertStringContainsString('attachment', $response->getHeaderLine('content-disposition'));
        self::assertStringContainsString('webtrees-logs.csv', $response->getHeaderLine('content-disposition'));
    }

    /**
     * EP4: PhpInformation GET → 200 + enthält phpinfo-Output.
     */
    public function test_php_information_returns_200(): void
    {
        $handler  = new PhpInformation();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
