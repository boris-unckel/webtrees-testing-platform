<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectModal;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToIndividualModal;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToRecordAction;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\MediaFileService;

/**
 * Komponentenintegrationstest: Medienobjekte anlegen & verknüpfen — E05.
 *
 * Tests:
 * - CreateMediaObjectModal GET → 200
 * - LinkMediaToRecordAction POST: MEDIA + INDI XREFs → 302
 * - LinkMediaToIndividualModal GET: MEDIA XREF → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToRecordAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkMediaToIndividualModal
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
}
