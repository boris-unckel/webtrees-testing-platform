<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\HeaderFactoryInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Header;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\HeaderPage;
use Fisharebest\Webtrees\Registry;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: HeaderPage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von HeaderPage ab:
 *   - sichtbarer Header mit korrektem Slug → 200 OK.
 *   - Header mit abweichendem Slug → 301 Moved Permanently (kanonische URL).
 *   - unbekannte Header-XREF → HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekt (Header) als Stub (Wert-orientiert),
 * Factory-Interfaces (HeaderFactoryInterface, SlugFactoryInterface) als Mock
 * mit Erwartungs-Verifikation (`expects($this->once())`) für den Factory-Aufruf.
 *
 * @see docs/tds_conditions_ref.md S25
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\HeaderPage
 */
class HeaderPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('headerpage', 'HeaderPage Test', self::DEMO_GED);
    }

    /**
     * Erfolgreicher Render setzt zusätzlich einen Link-Header mit rel="canonical"
     * auf die kanonische URL des Headers (eigenständige Verhaltens-Property,
     * komplementär zu Statuscode 200 in test_handle_returns_ok_for_visible_header).
     *
     * @group ported-l2-doubles
     */
    public function test_handle_sets_canonical_link_header_on_visible_header(): void
    {
        // Arrange
        $canonical_url = 'https://webtrees.test/header/H1';

        $header = self::createStub(Header::class);
        $header->method('xref')->willReturn('H1');
        $header->method('tree')->willReturn($this->tree);
        $header->method('canShow')->willReturn(true);
        $header->method('canEdit')->willReturn(false);
        $header->method('fullName')->willReturn('Test Header');
        $header->method('url')->willReturn($canonical_url);
        $header->method('facts')->willReturn(new Collection());

        $header_factory = $this->createMock(HeaderFactoryInterface::class);
        $header_factory
            ->expects($this->once())
            ->method('make')
            ->with('H1', $this->tree)
            ->willReturn($header);
        Registry::headerFactory($header_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $handler = new HeaderPage();
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'H1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertSame(
            '<' . $canonical_url . '>; rel="canonical"',
            $response->getHeaderLine('Link'),
        );
    }

    /**
     * Sichtbarer Header mit übereinstimmendem Slug rendert die record-page mit 200.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_header(): void
    {
        // Arrange
        $header = self::createStub(Header::class);
        $header->method('xref')->willReturn('H1');
        $header->method('tree')->willReturn($this->tree);
        $header->method('canShow')->willReturn(true);
        $header->method('canEdit')->willReturn(false);
        $header->method('fullName')->willReturn('Test Header');
        $header->method('url')->willReturn('https://webtrees.test/header/H1');
        $header->method('facts')->willReturn(new Collection());

        $header_factory = $this->createMock(HeaderFactoryInterface::class);
        $header_factory
            ->expects($this->once())
            ->method('make')
            ->with('H1', $this->tree)
            ->willReturn($header);
        Registry::headerFactory($header_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $handler = new HeaderPage();
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'H1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL des Headers.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $header = self::createStub(Header::class);
        $header->method('xref')->willReturn('H1');
        $header->method('tree')->willReturn($this->tree);
        $header->method('canShow')->willReturn(true);
        $header->method('canEdit')->willReturn(false);
        $header->method('url')->willReturn('https://webtrees.test/header/H1/test-header');

        $header_factory = $this->createMock(HeaderFactoryInterface::class);
        $header_factory
            ->expects($this->once())
            ->method('make')
            ->with('H1', $this->tree)
            ->willReturn($header);
        Registry::headerFactory($header_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('test-header');
        Registry::slugFactory($slug_factory);

        $handler = new HeaderPage();
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'H1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Header-XREF (Factory liefert null) löst HttpNotFoundException
     * aus Auth::checkHeaderAccess() aus.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_header_throws_not_found_exception(): void
    {
        // Arrange
        $header_factory = $this->createMock(HeaderFactoryInterface::class);
        $header_factory
            ->expects($this->once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::headerFactory($header_factory);

        $handler = new HeaderPage();
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
