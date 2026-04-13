<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpBadRequestException;
use Fisharebest\Webtrees\Http\RequestHandlers\AdminMediaFileDownload;
use Fisharebest\Webtrees\Http\RequestHandlers\FixLevel0MediaAction;
use Fisharebest\Webtrees\Http\RequestHandlers\FixLevel0MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ManageMediaPage;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PhpService;

/**
 * Komponentenintegrationstest: Medienverwaltung Admin (A08).
 *
 * Prüft die fünf Admin-Media-Handler: FixLevel0MediaPage (Seitenrender),
 * AdminMediaFileDownload (Pfad-Validierung/Security), FixLevel0MediaAction
 * (nicht-existente Records), ManageMediaPage (Seitenrender).
 *
 * @see docs/tds_conditions_ref.md A08
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\FixLevel0MediaPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AdminMediaFileDownload
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\FixLevel0MediaAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ManageMediaPage
 */
class AdminMediaManagementIntegrationTest extends MysqlTestCase
{
    /**
     * EP4/B3: FixLevel0MediaPage rendert die Admin-Seite.
     */
    public function test_fix_level0_media_page_renders(): void
    {
        $this->createAndLoginAdmin();

        $handler  = new FixLevel0MediaPage();
        $request  = $this->createRequest();
        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertNotEmpty($body);
    }

    /**
     * EP2/B2: AdminMediaFileDownload mit ungültigem Pfad → 400 Bad Request.
     */
    public function test_admin_media_download_invalid_path(): void
    {
        $this->createAndLoginAdmin();

        $mediaFileService = new MediaFileService(new PhpService());
        $handler          = new AdminMediaFileDownload($mediaFileService);

        $request = $this->createRequest(query: ['path' => 'nonexistent/invalid/file.jpg']);

        $this->expectException(HttpBadRequestException::class);
        $handler->handle($request);
    }

    /**
     * BVA: Path-Traversal-Versuch → 400 Bad Request (Sicherheitstest).
     */
    public function test_admin_media_download_path_traversal(): void
    {
        $this->createAndLoginAdmin();

        $mediaFileService = new MediaFileService(new PhpService());
        $handler          = new AdminMediaFileDownload($mediaFileService);

        $request = $this->createRequest(query: ['path' => '../../etc/passwd']);

        $this->expectException(HttpBadRequestException::class);
        $handler->handle($request);
    }

    /**
     * EP6/B5: FixLevel0MediaAction mit nicht-existenten Records → keine Änderung, leere Response.
     */
    public function test_fix_level0_action_nonexistent_records(): void
    {
        $admin = $this->createAndLoginAdmin();
        $uniqueName = 'media-test-' . substr(md5($this->name()), 0, 8);
        $this->tree = $this->treeService->create($uniqueName, 'Media Test');

        $handler = new FixLevel0MediaAction($this->treeService);

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'fact_id'   => 'nonexistent-fact',
                'indi_xref' => 'I999',
                'obje_xref' => 'M999',
                'tree_id'   => (string) $this->tree->id(),
            ],
            attributes: ['user' => $admin],
        );

        $response = $handler->handle($request);

        // FixLevel0MediaAction gibt response() ohne Argumente zurück → 204 No Content
        $this->assertSame(204, $response->getStatusCode());
    }

    /**
     * EP8/B7: ManageMediaPage rendert mit Standard-Parametern.
     */
    public function test_manage_media_page_renders(): void
    {
        $admin = $this->createAndLoginAdmin();

        $mediaFileService = new MediaFileService(new PhpService());
        $handler          = new ManageMediaPage($mediaFileService);

        $request  = $this->createRequest(attributes: ['user' => $admin]);
        $response = $handler->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertNotEmpty($body);
    }
}
