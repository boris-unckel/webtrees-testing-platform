<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Http\RequestHandlers\CheckTree;
use Fisharebest\Webtrees\Services\PhpService;
use Fisharebest\Webtrees\Services\TimeoutService;

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
}
