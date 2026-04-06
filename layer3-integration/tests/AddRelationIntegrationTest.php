<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualPage;
use Fisharebest\Webtrees\Services\GedcomEditService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: Person/Familie anlegen & verknüpfen — E01.
 *
 * Tests:
 * - AddChildToIndividualPage GET → 200
 * - AddChildToIndividualAction POST → 302 (neuer INDI+FAM in DB)
 * - DataProvider: AddParentToIndividualPage, AddSpouseToIndividualPage,
 *   AddChildToFamilyPage, AddSpouseToFamilyPage GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyPage
 * @see docs/testquality_improve_E01.md
 */
class AddRelationIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e01-addrel', 'E01 Add Relation', self::DEMO_GED);
    }

    /**
     * EP1: AddChildToIndividualPage GET mit gültiger XREF → 200.
     */
    public function test_add_child_to_individual_page_returns_200(): void
    {
        $handler = new AddChildToIndividualPage(new GedcomEditService());

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
     * EP3: AddChildToIndividualAction POST mit gültigen GEDCOM-Daten → 302.
     */
    public function test_add_child_to_individual_action_redirects(): void
    {
        $handler = new AddChildToIndividualAction(new GedcomEditService());

        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
            params: [
                'ilevels' => ['1'],
                'itags'   => ['SEX'],
                'ivalues' => ['U'],
                'url'     => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * @return array<string, array{class-string, string, string|null}>
     */
    public static function addRelationPageHandlers(): array
    {
        return [
            'add-parent-indi'  => [AddParentToIndividualPage::class, 'X1030', 'M'],
            'add-spouse-indi'  => [AddSpouseToIndividualPage::class, 'X1030', null],
            'add-child-fam'    => [AddChildToFamilyPage::class, 'f1', 'M'],
            'add-spouse-fam'   => [AddSpouseToFamilyPage::class, 'f1', 'M'],
        ];
    }

    /**
     * EP5: Weitere Page-Handler GET → 200 (DataProvider-Smoke).
     *
     * @param class-string $handlerClass
     */
    #[DataProvider('addRelationPageHandlers')]
    public function test_add_relation_page_returns_200(string $handlerClass, string $xref, ?string $sex): void
    {
        $handler = new $handlerClass(new GedcomEditService());

        $attributes = [
            'tree' => $this->tree,
            'xref' => $xref,
        ];
        if ($sex !== null) {
            $attributes['sex'] = $sex;
        }

        $request  = $this->createRequest(attributes: $attributes);
        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }
}
