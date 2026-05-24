<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualAction;
use Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkChildToFamilyAction;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkChildToFamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkSpouseToIndividualAction;
use Fisharebest\Webtrees\Http\RequestHandlers\LinkSpouseToIndividualPage;
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
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkChildToFamilyAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkChildToFamilyPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkSpouseToIndividualAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\LinkSpouseToIndividualPage
 * @see docs/tds_conditions_ref.md E01
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

    /**
     * AddChildToFamilyAction POST mit nicht existierender Family-XREF →
     * Auth::checkFamilyAccess(null) wirft HttpNotFoundException;
     * GedcomEditService::editLinesToGedcom darf nicht aufgerufen werden.
     *
     * @group ported-l2-doubles
     */
    public function test_add_child_to_family_action_throws_not_found_for_missing_family(): void
    {
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('editLinesToGedcom');

        $handler = new AddChildToFamilyAction($gedcom_edit_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'ilevels' => [],
                'itags'   => [],
                'ivalues' => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddChildToFamilyPage GET mit sex=F (Tochter) → 200.
     * Variante des bestehenden Provider-Smoke-Tests; prüft, dass der
     * Handler das sex-Attribut nicht restriktiv interpretiert und
     * GedcomEditService::newIndividualFacts mindestens einmal aufruft.
     *
     * @group ported-l2-doubles
     */
    public function test_add_child_to_family_page_returns_daughter_page(): void
    {
        $handler = new AddChildToFamilyPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'f1',
                'sex'  => 'F',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddChildToFamilyPage GET mit nicht existierender Family-XREF →
     * Auth::checkFamilyAccess(null) wirft HttpNotFoundException.
     *
     * @group ported-l2-doubles
     */
    public function test_add_child_to_family_page_throws_not_found_for_missing_family(): void
    {
        $handler = new AddChildToFamilyPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
                'sex'  => 'M',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddChildToIndividualAction POST mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException;
     * GedcomEditService::editLinesToGedcom darf nicht aufgerufen werden.
     *
     * @group ported-l2-doubles
     */
    public function test_add_child_to_individual_action_throws_not_found_for_missing_individual(): void
    {
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('editLinesToGedcom');

        $handler = new AddChildToIndividualAction($gedcom_edit_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'ilevels' => [],
                'itags'   => [],
                'ivalues' => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddChildToIndividualPage GET mit männlichem Eltern-Individual (X1031 = Prince Arthur) → 200.
     * Variante des bestehenden Tests test_add_child_to_individual_page_returns_200,
     * der über X1030 (Queen Elizabeth II) bereits den weiblichen Eltern-Fall
     * abdeckt. Diese Methode belegt, dass der Handler auch für sex='M'-Eltern
     * eine valide Edit-Page rendert (Differenzierung über Surname-Tradition).
     * Der zugehörige Quell-Stub-Test nutzt SurnameTraditionFactory-Registry-
     * Override; hier wird stattdessen der echte SurnameTradition-Pfad über
     * die GEDCOM-Daten der demo.ged getestet.
     *
     * @group ported-l2-doubles
     */
    public function test_add_child_to_individual_page_returns_200_for_male_parent(): void
    {
        // Arrange
        $handler = new AddChildToIndividualPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1031',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddChildToIndividualPage GET mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException.
     * GedcomEditService darf wegen des frühen Aussteigens nicht aufgerufen
     * werden — wird hier zur Absicherung als Mock mit ->never() übergeben.
     *
     * @group ported-l2-doubles
     */
    public function test_add_child_to_individual_page_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('newIndividualFacts');

        $handler = new AddChildToIndividualPage($gedcom_edit_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddParentToIndividualAction POST mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException;
     * GedcomEditService::editLinesToGedcom darf nicht aufgerufen werden.
     *
     * @group ported-l2-doubles
     */
    public function test_add_parent_to_individual_action_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('editLinesToGedcom');

        $handler = new AddParentToIndividualAction($gedcom_edit_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'ilevels' => [],
                'itags'   => [],
                'ivalues' => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddParentToIndividualPage GET mit sex=F (Mutter hinzufügen) → 200.
     * Der bestehende DataProvider-Smoke testet sex='M' (Vater); diese
     * Methode ergänzt den weiblichen Fall, damit beide
     * Surname-Tradition-Pfade (newParentNames für Vater vs. Mutter)
     * gegen die echten GEDCOM-Daten der demo.ged abgedeckt sind.
     *
     * @group ported-l2-doubles
     */
    public function test_add_parent_to_individual_page_returns_mother_page(): void
    {
        // Arrange
        $handler = new AddParentToIndividualPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
                'sex'  => 'F',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddParentToIndividualPage GET mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException.
     * GedcomEditService darf wegen des frühen Aussteigens nicht aufgerufen
     * werden — wird hier zur Absicherung als Mock mit ->never() übergeben.
     *
     * @group ported-l2-doubles
     */
    public function test_add_parent_to_individual_page_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('newIndividualFacts');

        $handler = new AddParentToIndividualPage($gedcom_edit_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
                'sex'  => 'M',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddSpouseToFamilyPage GET mit sex=F (Frau hinzufügen) → 200.
     * Der bestehende DataProvider-Smoke testet sex='M' (Mann); diese
     * Methode ergänzt den weiblichen Fall, damit beide
     * Surname-Tradition-Pfade (newSpouseNames für Mann vs. Frau)
     * gegen die echten GEDCOM-Daten der demo.ged abgedeckt sind.
     *
     * @group ported-l2-doubles
     */
    public function test_add_spouse_to_family_page_returns_wife_page(): void
    {
        // Arrange
        $handler = new AddSpouseToFamilyPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'f1',
                'sex'  => 'F',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddSpouseToFamilyPage GET mit nicht existierender Family-XREF →
     * Auth::checkFamilyAccess(null) wirft HttpNotFoundException.
     * GedcomEditService darf wegen des frühen Aussteigens nicht aufgerufen
     * werden — wird hier zur Absicherung als Mock mit ->never() übergeben.
     *
     * @group ported-l2-doubles
     */
    public function test_add_spouse_to_family_page_throws_not_found_for_missing_family(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('newIndividualFacts');

        $handler = new AddSpouseToFamilyPage($gedcom_edit_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
                'sex'  => 'F',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddSpouseToFamilyAction POST mit nicht existierender Family-XREF →
     * Auth::checkFamilyAccess(null) wirft HttpNotFoundException;
     * GedcomEditService::editLinesToGedcom darf nicht aufgerufen werden.
     *
     * @group ported-l2-doubles
     */
    public function test_add_spouse_to_family_action_throws_not_found_for_missing_family(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('editLinesToGedcom');

        $handler = new AddSpouseToFamilyAction($gedcom_edit_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'ilevels' => [],
                'itags'   => [],
                'ivalues' => [],
                'flevels' => [],
                'ftags'   => [],
                'fvalues' => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddSpouseToIndividualAction POST mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException;
     * GedcomEditService::editLinesToGedcom darf nicht aufgerufen werden.
     *
     * @group ported-l2-doubles
     */
    public function test_add_spouse_to_individual_action_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('editLinesToGedcom');

        $handler = new AddSpouseToIndividualAction($gedcom_edit_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'ilevels' => [],
                'itags'   => [],
                'ivalues' => [],
                'flevels' => [],
                'ftags'   => [],
                'fvalues' => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * AddSpouseToIndividualPage GET mit männlichem Ehepartner (X1031 = Prince
     * Arthur, sex='M') → 200 mit Frau-spezifischem Surname-Tradition-Pfad.
     * Der bestehende DataProvider-Smoke testet AddSpouseToIndividualPage mit
     * X1030 (Queen Elizabeth II = F → Mann hinzufügen). Diese Methode ergänzt
     * den symmetrischen Fall — bestehendes Individual ist männlich, also wird
     * eine Frau hinzugefügt — damit beide Surname-Tradition-Pfade
     * (newSpouseNames für Mann vs. Frau) gegen die echten GEDCOM-Daten der
     * demo.ged abgedeckt sind.
     *
     * @group ported-l2-doubles
     */
    public function test_add_spouse_to_individual_page_returns_wife_page(): void
    {
        // Arrange
        $handler = new AddSpouseToIndividualPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1031',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddSpouseToIndividualPage GET mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException.
     * GedcomEditService darf wegen des frühen Aussteigens nicht aufgerufen
     * werden — wird hier zur Absicherung als Mock mit ->never() übergeben.
     *
     * @group ported-l2-doubles
     */
    public function test_add_spouse_to_individual_page_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('newIndividualFacts');

        $handler = new AddSpouseToIndividualPage($gedcom_edit_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * LinkChildToFamilyAction POST mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException, bevor
     * die Family-Verknüpfung versucht wird. Der Handler hat keinen
     * GedcomEditService-Konstruktor-Parameter (im Gegensatz zu den
     * Add*Action-Handlern); deshalb keine Mock-Interaktion zu verifizieren.
     *
     * @group ported-l2-doubles
     */
    public function test_link_child_to_family_action_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $handler = new LinkChildToFamilyAction();
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'famid' => 'F1',
                'PEDI'  => '',
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * LinkChildToFamilyPage GET mit gültiger Individual-XREF → 200.
     * Der Handler hat keinen Konstruktor-Parameter und rendert das
     * Verknüpfungs-Formular für ein bestehendes Individuum.
     *
     * @group ported-l2-doubles
     */
    public function test_link_child_to_family_page_returns_200(): void
    {
        // Arrange
        $handler = new LinkChildToFamilyPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * LinkChildToFamilyPage GET mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException, bevor
     * der View gerendert wird.
     *
     * @group ported-l2-doubles
     */
    public function test_link_child_to_family_page_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $handler = new LinkChildToFamilyPage();
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }

    /**
     * LinkSpouseToIndividualAction POST mit gültigem Individual + Spouse →
     * 302-Redirect zur neu erstellten Familie. Prüft, dass der Handler
     * GedcomEditService::editLinesToGedcom genau einmal aufruft (Mock-Interaktion)
     * und eine neue Familie über tree->createFamily() entsteht. X1031 (Prince
     * Arthur, SEX=M) wird mit X1032 (Princess Mary, SEX=F) verknüpft — dies
     * deckt den Code-Pfad sex='M' → HUSB-zuerst ab.
     *
     * @group ported-l2-doubles
     */
    public function test_link_spouse_to_individual_action_redirects(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service
            ->expects(self::once())
            ->method('editLinesToGedcom')
            ->willReturn('');

        $handler = new LinkSpouseToIndividualAction($gedcom_edit_service);
        $request = $this->createRequest(
            method:     RequestMethodInterface::METHOD_POST,
            params: [
                'spid'    => 'X1032',
                'flevels' => [],
                'ftags'   => [],
                'fvalues' => [],
            ],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1031',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * LinkSpouseToIndividualPage GET mit weiblichem Individual (X1030 = Queen
     * Elizabeth II, sex='F') → 200 mit Mann-spezifischem Verknüpfungs-Pfad.
     * Der Handler verzweigt anhand des Geschlechts: F → "Add a husband"-Label,
     * andere Geschlechter → "Add a wife"-Label. Diese Methode deckt den
     * weiblichen Eingangsfall gegen die echten GEDCOM-Daten der demo.ged ab.
     *
     * @group ported-l2-doubles
     */
    public function test_link_spouse_to_individual_page_returns_200_for_female_individual(): void
    {
        // Arrange
        $handler = new LinkSpouseToIndividualPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * LinkSpouseToIndividualPage GET mit männlichem Individual (X1031 = Prince
     * Arthur, sex='M') → 200 mit Frau-spezifischem Verknüpfungs-Pfad.
     * Ergänzt den symmetrischen Geschlechts-Fall zum weiblichen Eingangsfall
     * und deckt den else-Zweig im Handler ab (label = "Wife").
     *
     * @group ported-l2-doubles
     */
    public function test_link_spouse_to_individual_page_returns_200_for_male_individual(): void
    {
        // Arrange
        $handler = new LinkSpouseToIndividualPage(new GedcomEditService());

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1031',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * LinkSpouseToIndividualPage GET mit nicht existierender Individual-XREF →
     * Auth::checkIndividualAccess(null) wirft HttpNotFoundException, bevor
     * der View gerendert wird. GedcomEditService darf wegen des frühen
     * Aussteigens nicht aufgerufen werden — wird hier zur Absicherung als
     * Mock mit ->never() übergeben.
     *
     * @group ported-l2-doubles
     */
    public function test_link_spouse_to_individual_page_throws_not_found_for_missing_individual(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('newIndividualFacts');

        $handler = new LinkSpouseToIndividualPage($gedcom_edit_service);
        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X_NONEXISTENT',
            ],
        );

        // Act + Assert
        $this->expectException(HttpNotFoundException::class);
        $handler->handle($request);
    }
}
