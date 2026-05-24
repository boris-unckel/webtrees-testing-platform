<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectFromFile;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectModal;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToFamilyModal;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToIndividualModal;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToRecordAction;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToSourceModal;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\PendingChangesService;

/**
 * Komponentenintegrationstest: Medienobjekte anlegen & verknüpfen — E05.
 *
 * Tests:
 * - CreateMediaObjectModal GET → 200
 * - LinkMediaToRecordAction POST: MEDIA + INDI XREFs → 302
 * - LinkMediaToIndividualModal GET: MEDIA XREF → 200
 * - CreateMediaObjectAction POST mit leerem Upload → 406
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToRecordAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToIndividualModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToFamilyModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectFromFile
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToSourceModal
 * @see docs/tds_conditions_ref.md E05
 * @see docs/testquality_improve_E05.md
 */
class MediaObjectIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED   = '/fixtures/demo.ged';
    private const MEDIA_XREF = 'X247';
    private const INDI_XREF  = 'X1030';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e05-media', 'E05 Media', self::DEMO_GED);
    }

    /**
     * EP1: CreateMediaObjectModal GET → 200.
     */
    public function test_create_media_object_modal_returns_200(): void
    {
        $handler = new CreateMediaObjectModal(
            Registry::container()->get(MediaFileService::class),
        );

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: LinkMediaToRecordAction POST: MEDIA-XREF + INDI-XREF → 302.
     */
    public function test_link_media_to_record_action_redirects(): void
    {
        $handler = new LinkMediaToRecordAction();

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree' => $this->tree,
                'xref' => self::MEDIA_XREF,
            ],
            params: [
                'link' => self::INDI_XREF,
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP5: LinkMediaToIndividualModal GET mit MEDIA-XREF → 200.
     */
    public function test_link_media_to_individual_modal_returns_200(): void
    {
        $handler = new LinkMediaToIndividualModal();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => self::MEDIA_XREF,
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * CreateMediaObjectAction: leerer Upload → 406 NOT_ACCEPTABLE.
     *
     * MediaFileService::uploadFile liefert einen leeren String zurück, wenn
     * keine Datei hochgeladen wurde. Der Handler antwortet dann mit
     * StatusCodeInterface::STATUS_NOT_ACCEPTABLE.
     *
     * @group ported-l2-doubles
     */
    public function test_create_media_object_action_returns_406_when_upload_empty(): void
    {
        // Arrange: Service-Mock liefert leeren String (kein Upload).
        $media_file_service = $this->createMock(MediaFileService::class);
        $media_file_service
            ->expects($this->once())
            ->method('uploadFile')
            ->willReturn('');

        $pending_changes_service = self::createStub(PendingChangesService::class);

        $handler = new CreateMediaObjectAction($media_file_service, $pending_changes_service);

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     ['media-note' => '', 'title' => 'Test', 'type' => '', 'restriction' => ''],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * CreateMediaObjectFromFile: handle() legt Medienobjekt aus existierender Datei an und liefert 302.
     *
     * Im Unterschied zu CreateMediaObjectAction wird hier keine Datei hochgeladen,
     * sondern ein bereits vorhandener Dateipfad als 'file'-Parameter referenziert.
     * Der Handler baut das OBJE-Gedcom selbst, ruft Tree::createRecord() und
     * PendingChangesService::acceptRecord() auf und antwortet mit einem 302-Redirect
     * auf die URL des neuen Records.
     *
     * @group ported-l2-doubles
     */
    public function test_create_media_object_from_file_creates_record_and_redirects(): void
    {
        // Arrange
        $handler = new CreateMediaObjectFromFile(
            Registry::container()->get(MediaFileService::class),
            Registry::container()->get(PendingChangesService::class),
        );

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params:     [
                'file'  => 'photo.jpg',
                'type'  => 'photo',
                'title' => 'A Photo',
                'note'  => '',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * LinkMediaToIndividualModal: unbekannter XREF → HttpNotFoundException.
     *
     * Auth::checkMediaAccess(null) wirft eine HttpNotFoundException, wenn
     * MediaFactory::make() für den XREF kein Record liefert. Analog zum
     * Family-Modal-Pendant nutzt der Test einen nicht existierenden XREF
     * gegen die MySQL-Instanz statt eines MediaFactoryInterface-Mocks.
     *
     * @group ported-l2-doubles
     */
    public function test_link_media_to_individual_modal_throws_not_found_when_media_missing(): void
    {
        // Arrange
        $handler = new LinkMediaToIndividualModal();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X9999',
            ],
        );

        // Assert + Act
        $this->expectException(HttpNotFoundException::class);

        $handler->handle($request);
    }

    /**
     * LinkMediaToFamilyModal: GET mit existierendem MEDIA-XREF → 200.
     *
     * Analog zu LinkMediaToIndividualModal liefert der Handler das
     * Verknüpfungs-Modal aus, sobald ein sichtbares Media-Objekt
     * über den XREF auflösbar ist.
     *
     * @group ported-l2-doubles
     */
    public function test_link_media_to_family_modal_returns_200_for_visible_media(): void
    {
        // Arrange
        $handler = new LinkMediaToFamilyModal();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => self::MEDIA_XREF,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * LinkMediaToFamilyModal: unbekannter XREF → HttpNotFoundException.
     *
     * Auth::checkMediaAccess(null) wirft eine HttpNotFoundException, wenn
     * MediaFactory::make() für den XREF kein Record liefert.
     *
     * @group ported-l2-doubles
     */
    public function test_link_media_to_family_modal_throws_not_found_when_media_missing(): void
    {
        // Arrange
        $handler = new LinkMediaToFamilyModal();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X9999',
            ],
        );

        // Assert + Act
        $this->expectException(HttpNotFoundException::class);

        $handler->handle($request);
    }

    /**
     * LinkMediaToSourceModal: GET mit existierendem MEDIA-XREF → 200.
     *
     * Analog zu LinkMediaToFamilyModal / LinkMediaToIndividualModal liefert
     * der Handler das Verknüpfungs-Modal aus, sobald ein sichtbares
     * Media-Objekt über den XREF auflösbar ist.
     *
     * @group ported-l2-doubles
     */
    public function test_link_media_to_source_modal_returns_200_for_visible_media(): void
    {
        // Arrange
        $handler = new LinkMediaToSourceModal();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => self::MEDIA_XREF,
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * LinkMediaToSourceModal: unbekannter XREF → HttpNotFoundException.
     *
     * Auth::checkMediaAccess(null) wirft eine HttpNotFoundException, wenn
     * MediaFactory::make() für den XREF kein Record liefert. Analog zum
     * Family-Modal-Pendant nutzt der Test einen nicht existierenden XREF
     * gegen die MySQL-Instanz statt eines MediaFactoryInterface-Mocks.
     *
     * @group ported-l2-doubles
     */
    public function test_link_media_to_source_modal_throws_not_found_when_media_missing(): void
    {
        // Arrange
        $handler = new LinkMediaToSourceModal();

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X9999',
            ],
        );

        // Assert + Act
        $this->expectException(HttpNotFoundException::class);

        $handler->handle($request);
    }
}
