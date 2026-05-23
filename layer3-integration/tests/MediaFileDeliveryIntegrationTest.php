<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\MediaFactoryInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaFileDownload;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaFileThumbnail;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Registry;

/**
 * Komponentenintegrationstest: MediaFileDownload & MediaFileThumbnail — E07.
 *
 * Guards:
 * - MediaFileThumbnail: XREF nicht gefunden → replacementImageResponse (kein Exception)
 * - MediaFileDownload: XREF nicht gefunden → HttpNotFoundException via Auth::checkMediaAccess
 * - MediaFileThumbnail: XREF gefunden, canShow=true, kein fact_id match → replacementImageResponse
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MediaFileDownload
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\MediaFileThumbnail
 * @see docs/testquality_improve_E07.md
 */
class MediaFileDeliveryIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    /**
     * EP1: MediaFileThumbnail — ungültige XREF → replacementImageResponse (kein 500).
     * Der Handler gibt einen Ersatz-Bild-Response zurück statt zu werfen.
     */
    public function test_thumbnail_unknown_xref_returns_replacement_image(): void
    {
        $this->createTreeWithGedcom('e07-thumb', 'E07 Thumb', self::DEMO_GED);
        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query: [
                'xref'        => 'DOESNOTEXIST',
                'fact_id'     => '',
                'disposition' => 'inline',
                's'           => 'invalid',
                'w'           => '100',
                'h'           => '100',
                'fit'         => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $response = $handler->handle($request);

        // replacementImageResponse returns HTTP 200 regardless of the status argument
        // (the status code is encoded in the placeholder image, not the HTTP response)
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: MediaFileThumbnail — XREF existiert, fact_id passt nicht → replacementImageResponse NOT_FOUND.
     */
    public function test_thumbnail_valid_xref_unknown_fact_id_returns_not_found(): void
    {
        $this->createTreeWithGedcom('e07-thumb2', 'E07 Thumb2', self::DEMO_GED);
        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query: [
                'xref'        => 'X247',
                'fact_id'     => 'NONEXISTENT_FACT',
                'disposition' => 'inline',
                's'           => 'invalid',
                'w'           => '100',
                'h'           => '100',
                'fit'         => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $response = $handler->handle($request);

        // No matching media file found → replacementImageResponse HTTP 200
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP3: MediaFileDownload — ungültige XREF → HttpNotFoundException.
     * Auth::checkMediaAccess() wirft wenn Media null ist.
     */
    public function test_download_unknown_xref_throws_not_found(): void
    {
        $this->createTreeWithGedcom('e07-dl', 'E07 Download', self::DEMO_GED);
        $this->createAndLoginAdmin();
        $handler = new MediaFileDownload();
        $request = $this->createRequest(
            query: [
                'xref'        => 'DOESNOTEXIST',
                'fact_id'     => '',
                'disposition' => 'inline',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP5: MediaFileThumbnail — Media existiert, canShow=false → replacementImageResponse (kein Throw).
     * Wenn der Viewer das Media-Objekt nicht sehen darf, liefert der Handler ein „forbidden"-
     * Ersatzbild (HTTP 200) statt eine Exception zu werfen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MediaFileThumbnailTest.php
     * @group ported-l2-doubles
     */
    public function test_thumbnail_media_not_visible_returns_replacement_image(): void
    {
        // Arrange — Tree, MediaFactory-Mock, Media-Stub mit canShow=false
        $this->createTreeWithGedcom('e07-thumb-forbidden', 'E07 Thumb Forbidden', self::DEMO_GED);

        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(false);

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new MediaFileThumbnail();
        $request = $this->createRequest(
            query:      [
                'xref'    => 'M1',
                'fact_id' => 'abc',
                'w'       => '100',
                'h'       => '100',
                'fit'     => 'contain',
            ],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — replacementImageResponse() liefert HTTP 200; Forbidden-Status steckt im Bild
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4: MediaFileDownload — Media gefunden, aber kein matching fact_id → replacementImageResponse.
     * Bei leerer mediaFiles()-Collection bzw. nicht passender fact_id fällt der Handler auf
     * das Ersatzbild (HTTP 200) zurück, ohne zu werfen.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/MediaFileDownloadTest.php
     * @group ported-l2-doubles
     */
    public function test_download_valid_media_no_matching_fact_id_returns_replacement_image(): void
    {
        // Arrange — Tree anlegen, MediaFactory mocken, Media-Stub mit leerer mediaFiles()-Collection
        $this->createTreeWithGedcom('e07-dl-fact', 'E07 Download Fact', self::DEMO_GED);

        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canShow')->willReturn(true);
        $media->method('canEdit')->willReturn(true);
        $media->method('mediaFiles')->willReturn(collect([]));

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects(self::once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new MediaFileDownload();
        $request = $this->createRequest(
            query:      ['xref' => 'M1', 'fact_id' => 'missing', 'disposition' => 'inline'],
            attributes: ['tree' => $this->tree, 'user' => $this->createAndLoginAdmin()],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — replacementImageResponse() liefert HTTP 200 (Statuscode steckt im Bild, nicht im Header)
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
