<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\IndividualFactoryInterface;
use Fisharebest\Webtrees\Contracts\SlugFactoryInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\UserService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: IndividualPage HTTP-Handler.
 *
 * Deckt die drei zentralen Verhaltenspfade von IndividualPage ab:
 *   - sichtbares Individual mit korrektem Slug -> 200 OK.
 *   - Individual mit abweichendem Slug -> 301 Moved Permanently (kanonische URL).
 *   - unbekannte Individual-XREF -> HttpNotFoundException.
 *
 * Stub/Mock-Konvention: Domain-Objekte (Individual, Date, Place) und
 * Factory-Interfaces werden als Stubs eingehaengt, weil die Pfade
 * vorrangig Wert-orientiert sind (Slug-Vergleich, Sichtbarkeitspruefung,
 * Factory-Lookup). ClipboardService und ModuleService werden als Mocks
 * gefuehrt, weil die Render-Pfade `pastableFacts`, `findByComponent` und
 * `findByInterface` aufrufen und Verhalten erwartet wird.
 *
 * @see docs/tds_conditions_ref.md S23
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage
 */
class IndividualPageIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTreeWithGedcom('individualpage', 'IndividualPage Test', self::DEMO_GED);
    }

    /**
     * Sichtbares Individual mit uebereinstimmendem Slug rendert die
     * individual-page mit 200.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_returns_ok_for_visible_individual(): void
    {
        // Arrange — echte Date/Place-Objekte, sonst TypeError im Age-Konstruktor.
        $birth_date  = new Date('');
        $death_date  = new Date('');
        $birth_place = new Place('', $this->tree);
        $death_place = new Place('', $this->tree);

        $individual = self::createStub(Individual::class);
        $individual->method('xref')->willReturn('I1');
        $individual->method('tree')->willReturn($this->tree);
        $individual->method('canShow')->willReturn(true);
        $individual->method('canEdit')->willReturn(false);
        $individual->method('fullName')->willReturn('John /Doe/');
        $individual->method('lifespan')->willReturn('(1900-1980)');
        $individual->method('url')->willReturn('https://webtrees.test/individual/I1');
        $individual->method('facts')->willReturn(new Collection());
        $individual->method('isDead')->willReturn(true);
        $individual->method('sex')->willReturn('M');
        $individual->method('sortName')->willReturn('DOE,JOHN');
        $individual->method('getBirthDate')->willReturn($birth_date);
        $individual->method('getDeathDate')->willReturn($death_date);
        $individual->method('getBirthPlace')->willReturn($birth_place);
        $individual->method('getDeathPlace')->willReturn($death_place);
        $individual->method('childFamilies')->willReturn(new Collection());
        $individual->method('spouseFamilies')->willReturn(new Collection());

        $individual_factory = self::createStub(IndividualFactoryInterface::class);
        $individual_factory->method('make')->willReturn($individual);
        Registry::individualFactory($individual_factory);

        $slug_factory = self::createStub(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('');
        Registry::slugFactory($slug_factory);

        $clipboard_service = $this->createMock(ClipboardService::class);
        $clipboard_service
            ->expects(self::once())
            ->method('pastableFacts')
            ->willReturn(new Collection());

        $module_service = $this->createMock(ModuleService::class);
        $module_service->method('findByComponent')->willReturn(new Collection());
        $module_service->method('findByInterface')->willReturn(new Collection());

        $user_service = self::createStub(UserService::class);

        $handler = new IndividualPage($clipboard_service, $module_service, $user_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'I1', 'slug' => ''],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * Bei Slug-Mismatch antwortet der Handler mit 301 Moved Permanently auf die
     * kanonische URL des Individuals.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_redirects_on_slug_mismatch(): void
    {
        // Arrange
        $individual = self::createStub(Individual::class);
        $individual->method('xref')->willReturn('I1');
        $individual->method('tree')->willReturn($this->tree);
        $individual->method('canShow')->willReturn(true);
        $individual->method('canEdit')->willReturn(false);
        $individual->method('url')->willReturn('https://webtrees.test/individual/I1/john-doe');

        $individual_factory = $this->createMock(IndividualFactoryInterface::class);
        $individual_factory
            ->expects(self::once())
            ->method('make')
            ->with('I1', $this->tree)
            ->willReturn($individual);
        Registry::individualFactory($individual_factory);

        $slug_factory = $this->createMock(SlugFactoryInterface::class);
        $slug_factory->method('make')->willReturn('john-doe');
        Registry::slugFactory($slug_factory);

        $clipboard_service = self::createStub(ClipboardService::class);
        $module_service    = self::createStub(ModuleService::class);
        $user_service      = self::createStub(UserService::class);

        $handler = new IndividualPage($clipboard_service, $module_service, $user_service);
        $request = $this->createRequest(
            RequestMethodInterface::METHOD_GET,
            [],
            [],
            ['tree' => $this->tree, 'xref' => 'I1', 'slug' => 'wrong-slug'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_MOVED_PERMANENTLY, $response->getStatusCode());
    }

    /**
     * Unbekannte Individual-XREF (Factory liefert null) loest
     * HttpNotFoundException aus Auth::checkIndividualAccess() aus.
     *
     * @group ported-l2-doubles
     */
    public function test_handle_with_unknown_individual_throws_not_found_exception(): void
    {
        // Arrange
        $individual_factory = $this->createMock(IndividualFactoryInterface::class);
        $individual_factory
            ->expects(self::once())
            ->method('make')
            ->with('X999', $this->tree)
            ->willReturn(null);
        Registry::individualFactory($individual_factory);

        $clipboard_service = self::createStub(ClipboardService::class);
        $module_service    = self::createStub(ModuleService::class);
        $user_service      = self::createStub(UserService::class);

        $handler = new IndividualPage($clipboard_service, $module_service, $user_service);
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
