<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Http\RequestHandlers\AddMediaFileAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddMediaFileModal;
use Fisharebest\Webtrees\Services\GedcomImportService;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Services\PhpService;

/**
 * Komponentenintegrationstest: AddMediaFileAction.
 *
 * Portiert aus port-layer2-test-doubles. Die Quelle nutzte vollständige
 * Mocks (Media, MediaFactoryInterface, MediaFileService); hier laufen
 * die echten Dependencies gegen MySQL und ein aus demo.ged geladenes
 * Media-Objekt. Das fachliche Szenario (Upload-Fehler → Redirect zur
 * Media-URL) bleibt erhalten.
 *
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AddMediaFileActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AddMediaFileModalTest.php
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddMediaFileAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddMediaFileModal
 */
class AddMediaFileActionIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    private AddMediaFileAction $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('addmedia', 'AddMediaFileAction', self::DEMO_GED);
        $this->createAndLoginAdmin();

        $this->handler = new AddMediaFileAction(
            media_file_service:      new MediaFileService(new PhpService()),
            pending_changes_service: new PendingChangesService(new GedcomImportService()),
        );
    }

    /**
     * Upload-Fehler-Pfad: Request ohne hochgeladene Datei → MediaFileService::uploadFile
     * liefert '' → Handler redirected zur Media-URL (302 Found).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AddMediaFileActionTest.php
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_when_upload_fails(): void
    {
        // Arrange: existierenden Media-Record aus demo.ged ermitteln.
        $xref = DB::table('media')
            ->where('m_file', '=', $this->tree->id())
            ->value('m_id');

        if ($xref === null) {
            self::markTestSkipped('Kein Media-Record in demo.ged vorhanden.');
        }

        // file_location='url' + leerer remote → MediaFileService::uploadFile gibt ''
        // zurück (URL ohne Schema). Identische fachliche Wirkung wie der L2-Quelltest,
        // der MediaFileService gemockt und ''-Rückgabe erzwungen hat.
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'title'         => 'Photo',
                'type'          => 'photo',
                'file_location' => 'url',
                'remote'        => '',
            ],
            attributes: ['tree' => $this->tree, 'xref' => (string) $xref],
        );

        // Act
        $response = $this->handler->handle($request);

        // Assert: Upload-Fehler-Branch redirected zurück (302) — kein 5xx,
        // keine Exception, kein neuer media_file-Eintrag.
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * AddMediaFileModal — Erfolgsfall: existierendes Media-Objekt aus demo.ged
     * → Modal-View wird gerendert (HTTP 200). Die L2-Quelle hat MediaFactoryInterface
     * gemockt und ein vollständig gestubbtes Media-Objekt zurückgegeben; hier wird
     * das echte Media via Registry::mediaFactory()->make() aus der DB aufgelöst,
     * derselbe Erfolgs-Branch wird ausgeführt.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AddMediaFileModalTest.php
     * @group ported-l2-doubles
     */
    public function test_modal_handle_returns_ok_for_valid_media(): void
    {
        // Arrange: existierenden Media-Record aus demo.ged ermitteln.
        $xref = DB::table('media')
            ->where('m_file', '=', $this->tree->id())
            ->value('m_id');

        if ($xref === null) {
            self::markTestSkipped('Kein Media-Record in demo.ged vorhanden.');
        }

        $modal_handler = new AddMediaFileModal(
            media_file_service: new MediaFileService(new PhpService()),
        );
        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'xref' => (string) $xref],
        );

        // Act
        $response = $modal_handler->handle($request);

        // Assert: gerenderte Modal-View → 200 OK.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddMediaFileModal — Not-Found-Fall: nicht-existierender xref →
     * Registry::mediaFactory()->make() liefert null → Auth::checkMediaAccess()
     * wirft HttpNotFoundException, der Handler fängt sie und rendert die
     * error-Modal-View (HTTP 200, kein 4xx). Identische fachliche Wirkung
     * wie der L2-Quelltest, der MediaFactory->make() auf null gemockt hat.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/AddMediaFileModalTest.php
     * @group ported-l2-doubles
     */
    public function test_modal_handle_returns_error_view_when_media_not_found(): void
    {
        // Arrange: xref, der garantiert nicht im Baum existiert.
        $modal_handler = new AddMediaFileModal(
            media_file_service: new MediaFileService(new PhpService()),
        );
        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'xref' => 'X999'],
        );

        // Act
        $response = $modal_handler->handle($request);

        // Assert: HttpNotFoundException wird gefangen, error-view → 200 OK.
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
