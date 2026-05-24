<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\RepositoryFactoryInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: RepositoryPage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von RepositoryPage ab:
 *   - sichtbares Repository mit korrektem Slug -> 200 OK.
 *   - Repository mit abweichendem Slug -> 301 Moved Permanently (kanonische URL).
 *   - unbekannte Repository-XREF -> HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekte (Repository) werden als Stubs eingehaengt;
 * Factory-Interfaces (RepositoryFactoryInterface, SlugFactoryInterface) und
 * Services (ClipboardService, LinkedRecordService) werden als Mocks gefuehrt,
 * wenn Verhalten (Aufrufzahlen, Argumente) verifiziert wird.
 *
 * @see docs/tds_conditions_ref.md S29
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage
 */
class RepositoryPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('repopage', 'RepositoryPage Test', self::DEMO_GED);
    }

    /**
     * Sichtbares Repository mit uebereinstimmendem Slug rendert die
     * repository-page mit 200.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_repository(): void
    {
        // Arrange
        $repository = self::createStub(Repository::class);
        $repository->method('xref')->willReturn('R1');
        $repository->method('tree')->willReturn($this->tree);
        $repository->method('canShow')->willReturn(true);
        $repository->method('canEdit')->willReturn(false);
        $repository->method('fullName')->willReturn('Test Repository');
        $repository->method('url')->willReturn('https://webtrees.test/repository/R1');
        $repository->method('facts')->willReturn(new Collection());

        $repository_factory = $this->createMock(RepositoryFactoryInterface::class);
        $repository_factory
            ->expects(self::once())
            ->method('make')
            ->with('R1', $this->tree)
            ->willReturn($repository);
        Registry::repositoryFactory($repository_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects(self::once())
            ->method('pastableFacts')
            ->willReturn(new Collection());

        $linked_record_service = $this->createMock(LinkedRecordService::class);
        $linked_record_service
            ->expects(self::once())
            ->method('linkedSources')
            ->willReturn(new Collection());

        $handler = new RepositoryPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'R1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL des Repositorys.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $repository = self::createStub(Repository::class);
        $repository->method('xref')->willReturn('R1');
        $repository->method('tree')->willReturn($this->tree);
        $repository->method('canShow')->willReturn(true);
        $repository->method('canEdit')->willReturn(false);
        $repository->method('url')->willReturn('https://webtrees.test/repository/R1/test-repo');

        $repository_factory = $this->createMock(RepositoryFactoryInterface::class);
        $repository_factory
            ->expects(self::once())
            ->method('make')
            ->with('R1', $this->tree)
            ->willReturn($repository);
        Registry::repositoryFactory($repository_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('test-repo');
        Registry::slugFactory($slug_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new RepositoryPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'R1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Repository-XREF (Factory liefert null) loest
     * HttpNotFoundException aus Auth::checkRepositoryAccess() aus.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_repository_throws_not_found_exception(): void
    {
        // Arrange
        $repository_factory = $this->createMock(RepositoryFactoryInterface::class);
        $repository_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::repositoryFactory($repository_factory);

        $clipboard_service     = self::createStub(ClipboardService::class);
        $linked_record_service = self::createStub(LinkedRecordService::class);

        $handler = new RepositoryPage($clipboard_service, $linked_record_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'X999', 'slug' => ''],
        );

        // Assert
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }
}
