<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\IndividualFactoryInterface;
use Fisharebest\Webtrees\Contracts\MediaFactoryInterface;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderChildrenAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderChildrenPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderFamiliesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderFamiliesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaFilesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaFilesPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderNamesAction;
use Fisharebest\Webtrees\Http\RequestHandlers\ReorderNamesPage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: Sortierung (Reorder) — E06.
 *
 * Tests:
 * - ReorderChildrenPage GET: gültige FAM-XREF + Manager-Auth → 200
 * - ReorderChildrenPage GET: ungültige FAM-XREF → HttpNotFoundException
 * - ReorderNamesPage GET: gültige INDI-XREF + Manager-Auth → 200
 * - ReorderNamesPage GET: unbekannte INDI-XREF → HttpNotFoundException
 * - ReorderFamiliesPage GET: gültige INDI-XREF → 200
 * - ReorderChildrenAction POST: unbekannte FAM-XREF → HttpNotFoundException
 * - ReorderFamiliesAction POST: unbekannte INDI-XREF → HttpNotFoundException
 * - ReorderMediaAction POST: unbekannte INDI-XREF → HttpNotFoundException
 * - ReorderMediaFilesAction POST: sortiert OBJE:FILE-Facts und leitet weiter
 * - ReorderMediaFilesAction POST: unbekannte Media-XREF → HttpNotFoundException
 * - ReorderMediaFilesPage GET: Medium mit mehreren Dateien → 200
 * - ReorderMediaFilesPage GET: Medium mit einer Datei → 302 (Redirect MediaPage)
 * - ReorderMediaFilesPage GET: unbekannte Media-XREF → HttpNotFoundException
 * - ReorderMediaPage GET: gültige INDI-XREF → 200
 * - ReorderMediaPage GET: unbekannte INDI-XREF → HttpNotFoundException
 * - ReorderNamesAction POST: sortiert INDI:NAME-Facts und leitet weiter
 * - ReorderNamesAction POST: unbekannte INDI-XREF → HttpNotFoundException
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderChildrenAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderChildrenPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderFamiliesAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderNamesAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderNamesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderFamiliesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaFilesAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaFilesPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ReorderMediaPage
 * @see docs/tds_conditions_ref.md E06
 * @see docs/testquality_improve_E06.md
 */
class ReorderIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e06-reorder', 'E06 Reorder', self::DEMO_GED);
    }

    /**
     * EP1: ReorderChildrenPage GET mit gültiger FAM-XREF → 200.
     */
    public function test_reorder_children_page_with_valid_family_returns_200(): void
    {
        $handler = new ReorderChildrenPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'f1',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: ReorderChildrenPage GET mit ungültiger FAM-XREF → HttpNotFoundException.
     */
    public function test_reorder_children_page_with_unknown_family_throws_not_found(): void
    {
        $handler = new ReorderChildrenPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'DOESNOTEXIST',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP3: ReorderNamesPage GET mit gültiger INDI-XREF → 200.
     */
    public function test_reorder_names_page_with_valid_individual_returns_200(): void
    {
        $handler = new ReorderNamesPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP3b: ReorderNamesPage GET mit unbekannter INDI-XREF →
     * IndividualFactory liefert null → Auth::checkIndividualAccess wirft
     * HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_names_page_with_unknown_individual_throws_not_found(): void
    {
        // Arrange — IndividualFactory liefert für 'X999' kein Individual-Objekt
        $individual_factory = $this->createMock(IndividualFactoryInterface::class);
        $individual_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);

        Registry::individualFactory($individual_factory);

        $handler = new ReorderNamesPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X999',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP4: ReorderFamiliesPage GET mit gültiger INDI-XREF → 200.
     */
    public function test_reorder_families_page_with_valid_individual_returns_200(): void
    {
        $handler = new ReorderFamiliesPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP4b: ReorderFamiliesPage GET mit unbekannter INDI-XREF → HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_families_page_with_unknown_individual_throws_not_found(): void
    {
        $handler = new ReorderFamiliesPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP5: ReorderChildrenAction POST mit unbekannter FAM-XREF → HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_children_action_with_unknown_family_throws_not_found(): void
    {
        $handler = new ReorderChildrenAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => []],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP6: ReorderFamiliesAction POST mit unbekannter INDI-XREF → HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_families_action_with_unknown_individual_throws_not_found(): void
    {
        $handler = new ReorderFamiliesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => []],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP7: ReorderMediaAction POST mit unbekannter INDI-XREF → HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_action_with_unknown_individual_throws_not_found(): void
    {
        $handler = new ReorderMediaAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => []],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP8: ReorderMediaFilesAction POST sortiert OBJE:FILE-Facts gemäß
     * übergebener Order-Liste, ruft `updateRecord` auf dem Media-Mock auf
     * und liefert eine HTTP-302-Weiterleitung auf die Media-URL.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_files_action_reorders_files_and_redirects(): void
    {
        // Arrange — drei Facts: zwei OBJE:FILE (sortierbar) und ein Nicht-FILE-Fact (bleibt)
        $file_fact1 = self::createStub(Fact::class);
        $file_fact1->method('id')->willReturn('file-1');
        $file_fact1->method('tag')->willReturn('OBJE:FILE');
        $file_fact1->method('gedcom')->willReturn('1 FILE photo.jpg');

        $file_fact2 = self::createStub(Fact::class);
        $file_fact2->method('id')->willReturn('file-2');
        $file_fact2->method('tag')->willReturn('OBJE:FILE');
        $file_fact2->method('gedcom')->willReturn('1 FILE scan.png');

        $other_fact = self::createStub(Fact::class);
        $other_fact->method('id')->willReturn('note-1');
        $other_fact->method('tag')->willReturn('OBJE:NOTE');
        $other_fact->method('gedcom')->willReturn('1 NOTE A note');

        $media = $this->createMock(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canEdit')->willReturn(true);
        $media->method('canShow')->willReturn(true);
        $media->method('url')->willReturn('https://webtrees.test/media/M1');
        $media->method('facts')->willReturn(new Collection([$file_fact1, $file_fact2, $other_fact]));
        $media
            ->expects($this->once())
            ->method('updateRecord');

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new ReorderMediaFilesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => ['file-2', 'file-1']],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'M1',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Handler antwortet mit HTTP 302 (redirect auf Media-URL)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP9: ReorderMediaFilesAction POST mit unbekannter Media-XREF →
     * MediaFactory liefert null → Auth::checkMediaAccess wirft
     * HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_files_action_with_unknown_media_throws_not_found(): void
    {
        // Arrange — MediaFactory liefert für 'X999' kein Media-Objekt
        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);

        Registry::mediaFactory($media_factory);

        $handler = new ReorderMediaFilesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => []],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X999',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP10: ReorderMediaFilesPage GET für ein Medium mit mehreren Dateien
     * rendert die Sortier-Seite mit HTTP 200.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_files_page_with_multi_file_media_returns_200(): void
    {
        // Arrange — Media-Stub liefert zwei MediaFile-Stubs (Sortier-Voraussetzung erfüllt)
        $file1 = self::createStub(MediaFile::class);
        $file2 = self::createStub(MediaFile::class);

        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canEdit')->willReturn(true);
        $media->method('canShow')->willReturn(true);
        $media->method('fullName')->willReturn('Test Media');
        $media->method('url')->willReturn('https://webtrees.test/media/M1');
        $media->method('mediaFiles')->willReturn(new Collection([$file1, $file2]));

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new ReorderMediaFilesPage();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: [
                'tree' => $this->tree,
                'xref' => 'M1',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP11: ReorderMediaFilesPage GET für ein Medium mit nur einer Datei
     * leitet auf die MediaPage weiter (HTTP 302).
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_files_page_with_single_file_media_redirects(): void
    {
        // Arrange — Media-Stub mit nur einer Datei (mediaFiles()->count() < 2)
        $file1 = self::createStub(MediaFile::class);

        $media = self::createStub(Media::class);
        $media->method('xref')->willReturn('M1');
        $media->method('tree')->willReturn($this->tree);
        $media->method('canEdit')->willReturn(true);
        $media->method('canShow')->willReturn(true);
        $media->method('fullName')->willReturn('Test Media');
        $media->method('url')->willReturn('https://webtrees.test/media/M1');
        $media->method('mediaFiles')->willReturn(new Collection([$file1]));

        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('M1', $this->tree)
            ->willReturn($media);

        Registry::mediaFactory($media_factory);

        $handler = new ReorderMediaFilesPage();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: [
                'tree' => $this->tree,
                'xref' => 'M1',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP12: ReorderMediaFilesPage GET mit unbekannter Media-XREF →
     * MediaFactory liefert null → Auth::checkMediaAccess wirft
     * HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_files_page_with_unknown_media_throws_not_found(): void
    {
        // Arrange — MediaFactory liefert für 'X999' kein Media-Objekt
        $media_factory = $this->createMock(MediaFactoryInterface::class);
        $media_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);

        Registry::mediaFactory($media_factory);

        $handler = new ReorderMediaFilesPage();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X999',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP13: ReorderMediaPage GET mit gültiger INDI-XREF → 200.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_page_with_valid_individual_returns_200(): void
    {
        $handler = new ReorderMediaPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP14: ReorderMediaPage GET mit unbekannter INDI-XREF →
     * IndividualFactory liefert null → Auth::checkIndividualAccess wirft
     * HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_media_page_with_unknown_individual_throws_not_found(): void
    {
        $handler = new ReorderMediaPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * EP15: ReorderNamesAction POST sortiert INDI:NAME-Facts gemäß
     * übergebener Order-Liste, ruft `updateRecord` auf dem Individual-Mock
     * auf und liefert eine HTTP-302-Weiterleitung auf die Individual-URL.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_names_action_reorders_names_and_redirects(): void
    {
        // Arrange — drei Facts: zwei INDI:NAME (sortierbar) und ein Nicht-NAME-Fact (bleibt)
        $name_fact1 = self::createStub(Fact::class);
        $name_fact1->method('id')->willReturn('name-1');
        $name_fact1->method('tag')->willReturn('INDI:NAME');
        $name_fact1->method('gedcom')->willReturn('1 NAME John /Doe/');

        $name_fact2 = self::createStub(Fact::class);
        $name_fact2->method('id')->willReturn('name-2');
        $name_fact2->method('tag')->willReturn('INDI:NAME');
        $name_fact2->method('gedcom')->willReturn('1 NAME Johann /Doe/');

        $other_fact = self::createStub(Fact::class);
        $other_fact->method('id')->willReturn('birt-1');
        $other_fact->method('tag')->willReturn('INDI:BIRT');
        $other_fact->method('gedcom')->willReturn('1 BIRT');

        $individual = $this->createMock(Individual::class);
        $individual->method('xref')->willReturn('X1');
        $individual->method('tree')->willReturn($this->tree);
        $individual->method('canEdit')->willReturn(true);
        $individual->method('canShow')->willReturn(true);
        $individual->method('url')->willReturn('https://webtrees.test/individual/X1');
        $individual->method('facts')->willReturn(new Collection([$name_fact1, $name_fact2, $other_fact]));
        $individual
            ->expects($this->once())
            ->method('updateRecord');

        $individual_factory = $this->createMock(IndividualFactoryInterface::class);
        $individual_factory
            ->expects($this->once())
            ->method('make')
            ->with('X1', $this->tree)
            ->willReturn($individual);

        Registry::individualFactory($individual_factory);

        $handler = new ReorderNamesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => ['name-2', 'name-1']],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert — Handler antwortet mit HTTP 302 (redirect auf Individual-URL)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP16: ReorderNamesAction POST mit unbekannter INDI-XREF →
     * IndividualFactory liefert null → Auth::checkIndividualAccess wirft
     * HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_reorder_names_action_with_unknown_individual_throws_not_found(): void
    {
        // Arrange — IndividualFactory liefert für 'X999' kein Individual-Objekt
        $individual_factory = $this->createMock(IndividualFactoryInterface::class);
        $individual_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);

        Registry::individualFactory($individual_factory);

        $handler = new ReorderNamesAction();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['order' => []],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X999',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }
}
