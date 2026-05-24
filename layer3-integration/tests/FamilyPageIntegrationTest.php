<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\FamilyFactoryInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: FamilyPage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von FamilyPage ab:
 *   - sichtbare Family mit korrektem Slug → 200 OK.
 *   - Family mit abweichendem Slug → 301 Moved Permanently (kanonische URL).
 *   - unbekannte Family-XREF → HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekte (Family) und Factory-Interfaces
 * werden als Stubs eingehängt, weil die Pfade vorrangig Wert-orientiert
 * sind (Slug-Vergleich, Sichtbarkeitsprüfung, Factory-Lookup).
 *
 * @see docs/tds_conditions_ref.md S24
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage
 */
class FamilyPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('familypage', 'FamilyPage Test', self::DEMO_GED);
    }

    /**
     * Sichtbare Family mit übereinstimmendem Slug rendert die family-page mit 200.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_family(): void
    {
        // Arrange
        $family = self::createStub(Family::class);
        $family->method('xref')->willReturn('F1');
        $family->method('tree')->willReturn($this->tree);
        $family->method('canShow')->willReturn(true);
        $family->method('canEdit')->willReturn(false);
        $family->method('fullName')->willReturn('Husband / Wife');
        $family->method('url')->willReturn('https://webtrees.test/family/F1');
        $family->method('facts')->willReturn(new Collection());
        $family->method('spouses')->willReturn(new Collection());
        $family->method('children')->willReturn(new Collection());

        $family_factory = self::createStub(FamilyFactoryInterface::class);
        $family_factory->method('make')->willReturn($family);
        Registry::familyFactory($family_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $clipboard_service = self::createStub(ClipboardService::class);
        $clipboard_service->method('pastableFacts')->willReturn(new Collection());

        $handler = new FamilyPage($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'F1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL der Family.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $family = self::createStub(Family::class);
        $family->method('xref')->willReturn('F1');
        $family->method('tree')->willReturn($this->tree);
        $family->method('canShow')->willReturn(true);
        $family->method('canEdit')->willReturn(false);
        $family->method('fullName')->willReturn('Husband / Wife');
        $family->method('url')->willReturn('https://webtrees.test/family/F1/husband-wife');

        $family_factory = self::createStub(FamilyFactoryInterface::class);
        $family_factory->method('make')->willReturn($family);
        Registry::familyFactory($family_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('husband-wife');
        Registry::slugFactory($slug_factory);

        $clipboard_service = self::createStub(ClipboardService::class);

        $handler = new FamilyPage($clipboard_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'F1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Family-XREF (Factory liefert null) löst HttpNotFoundException
     * aus Auth::checkFamilyAccess() aus.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_family_throws_not_found_exception(): void
    {
        // Arrange
        $family_factory = self::createStub(FamilyFactoryInterface::class);
        $family_factory->method('make')->willReturn(null);
        Registry::familyFactory($family_factory);

        $clipboard_service = self::createStub(ClipboardService::class);

        $handler = new FamilyPage($clipboard_service);
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
