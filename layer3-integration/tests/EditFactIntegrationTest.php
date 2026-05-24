<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Contracts\GedcomRecordFactoryInterface;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\AddNewFact;
use Fisharebest\Webtrees\Http\RequestHandlers\DeleteFact;
use Fisharebest\Webtrees\Http\RequestHandlers\EditFactAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditFactPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SelectNewFact;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\ModuleService;
use Illuminate\Support\Collection;

/**
 * Komponentenintegrationstest: Fakten bearbeiten — E02.
 *
 * Tests:
 * - EditFactPage GET: ungültige fact_id → redirect
 * - DeleteFact POST: ungültige fact_id → 204 (kein Delete)
 * - AddNewFact GET → 200
 * - DeleteFact POST: matching + canEdit → record->deleteFact() aufgerufen → 204
 * - DeleteFact POST: matching + !canEdit → record->deleteFact() NICHT aufgerufen → 204
 * - DeleteFact POST: unknown xref → HttpNotFoundException
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditFactPage
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\DeleteFact
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\AddNewFact
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\SelectNewFact
 * @see docs/tds_conditions_ref.md E02
 * @see docs/testquality_improve_E02.md
 */
class EditFactIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e02-editfact', 'E02 EditFact', self::DEMO_GED);
    }

    /**
     * EP2: EditFactPage GET — fact_id nicht gefunden → redirect zu Record-URL.
     */
    public function test_edit_fact_page_redirects_for_unknown_fact_id(): void
    {
        $handler = new EditFactPage(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => 'NONEXISTENT_FACT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());
    }

    /**
     * EP5: DeleteFact POST — gültige XREF, ungültige fact_id → kein Delete → response() 204.
     * Die Schleife über record->facts() findet keinen Treffer, Loop-Body wird nicht ausgeführt.
     */
    public function test_delete_fact_returns_204_for_unknown_fact_id(): void
    {
        $handler = new DeleteFact();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => 'NONEXISTENT_FACT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * EP3: AddNewFact GET → 200.
     */
    public function test_add_new_fact_page_returns_200(): void
    {
        $handler = new AddNewFact(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
                'fact' => 'BIRT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * AddNewFact GET mit gültiger XREF-Form, aber nicht im Baum vorhandener XREF →
     * GedcomRecordFactory::make() liefert null → Auth::checkRecordAccess(null, true)
     * wirft HttpNotFoundException. Der Quell-Stub-Test ersetzt die Factory per
     * Registry-Override; hier wird stattdessen der echte „Record fehlt"-Pfad gegen
     * MySQL geprüft.
     *
     * @group ported-l2-doubles
     */
    public function test_add_new_fact_throws_not_found_for_unknown_xref(): void
    {
        $handler = new AddNewFact(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X999',
                'fact' => 'BIRT',
            ],
        );

        $this->expectException(HttpNotFoundException::class);

        $handler->handle($request);
    }

    /**
     * AddNewFact GET mit include_hidden=true Query-Parameter → 200.
     * Der zugehörige Quell-Stub-Test simuliert über einen Mock von
     * GedcomEditService::insertMissingFactSubtags den Branch, in dem die
     * sichtbare und die versteckte GEDCOM-Darstellung differieren und damit
     * eine hidden_url im View-Modell gesetzt wird. In L3 wird derselbe
     * Hidden-Pfad real über den include_hidden=true Query-Parameter aktiviert.
     *
     * @group ported-l2-doubles
     */
    public function test_add_new_fact_page_with_include_hidden_returns_200(): void
    {
        $handler = new AddNewFact(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            query: ['include_hidden' => '1'],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
                'fact' => 'BIRT',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * DeleteFact POST: Genau ein Fact am Record matcht die fact_id UND ist
     * editierbar → record->deleteFact() wird genau einmal mit dieser fact_id
     * (und update_change_log=true) aufgerufen; Handler liefert 204.
     *
     * @group ported-l2-doubles
     */
    public function test_delete_fact_handle_deletes_matching_editable_fact(): void
    {
        // Arrange
        $fact = self::createStub(Fact::class);
        $fact->method('id')->willReturn('fact-123');
        $fact->method('canEdit')->willReturn(true);

        $record = $this->createMock(GedcomRecord::class);
        $record->method('xref')->willReturn('X1');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('facts')->willReturn(new Collection([$fact]));
        $record
            ->expects($this->once())
            ->method('deleteFact')
            ->with('fact-123', true);

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);

        Registry::gedcomRecordFactory($record_factory);

        $handler = new DeleteFact();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1',
                'fact_id' => 'fact-123',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * DeleteFact POST: Fact am Record matcht die fact_id, ist aber nicht
     * editierbar (canEdit()=false) → record->deleteFact() wird NICHT
     * aufgerufen; Handler liefert dennoch 204.
     *
     * @group ported-l2-doubles
     */
    public function test_delete_fact_handle_skips_non_editable_fact(): void
    {
        // Arrange
        $fact = self::createStub(Fact::class);
        $fact->method('id')->willReturn('fact-123');
        $fact->method('canEdit')->willReturn(false);

        $record = $this->createMock(GedcomRecord::class);
        $record->method('xref')->willReturn('X1');
        $record->method('tree')->willReturn($this->tree);
        $record->method('canEdit')->willReturn(true);
        $record->method('canShow')->willReturn(true);
        $record->method('facts')->willReturn(new Collection([$fact]));
        $record
            ->expects($this->never())
            ->method('deleteFact');

        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn($record);

        Registry::gedcomRecordFactory($record_factory);

        $handler = new DeleteFact();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1',
                'fact_id' => 'fact-123',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * DeleteFact POST: GedcomRecordFactory::make() liefert null →
     * Auth::checkRecordAccess() wirft HttpNotFoundException — der Handler
     * darf die Exception nicht maskieren.
     *
     * @group ported-l2-doubles
     */
    public function test_delete_fact_handle_throws_not_found_for_unknown_record(): void
    {
        // Arrange
        $record_factory = self::createStub(GedcomRecordFactoryInterface::class);
        $record_factory->method('make')->willReturn(null);

        Registry::gedcomRecordFactory($record_factory);

        $handler = new DeleteFact();
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X999',
                'fact_id' => 'fact-123',
            ],
        );

        // Assert (Exception-Erwartung vor Act)
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * EditFactAction POST: XREF formal gültig, aber im Baum nicht vorhanden →
     * Registry::gedcomRecordFactory()->make() liefert null →
     * Auth::checkRecordAccess(null, true) wirft HttpNotFoundException, bevor
     * GedcomEditService::editLinesToGedcom() überhaupt aufgerufen wird.
     *
     * Der Quell-Stub-Test legt einen Tree per TreeService::create() an und
     * mockt GedcomEditService mit ->expects(self::never()). Hier wird stattdessen
     * der reale „Record fehlt"-Pfad gegen MySQL geprüft: ein nicht vorhandener
     * XREF im realen Tree.
     *
     * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditFactAction
     * @group ported-l2-doubles
     */
    public function test_edit_fact_action_handle_throws_not_found_for_unknown_record(): void
    {
        // Arrange
        $gedcom_edit_service = $this->createMock(GedcomEditService::class);
        $gedcom_edit_service->expects(self::never())->method('editLinesToGedcom');

        $module_service = $this->createMock(ModuleService::class);

        $handler = new EditFactAction($gedcom_edit_service, $module_service);
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['levels' => [], 'tags' => [], 'values' => []],
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X_NONEXISTENT',
                'fact_id' => 'new',
            ],
        );

        // Assert (Exception-Erwartung vor Act)
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * EditFactPage GET happy path: gültige XREF + gültige fact_id (eines vorhandenen,
     * editierbaren Facts) → Handler liefert 200 mit gerenderter Edit-Maske.
     *
     * Der Quell-Stub-Test simuliert den Fact über self::createStub(Fact::class) und
     * ein gemocktes GedcomEditService::insertMissingFactSubtags(). Hier wird der
     * happy path real über einen aus dem importierten demo.ged stammenden Record
     * (X1030) abgebildet — die erste vorhandene Fact-ID wird zur Laufzeit ermittelt.
     *
     * @group ported-l2-doubles
     */
    public function test_edit_fact_page_returns_200_for_valid_fact_id(): void
    {
        // Arrange — echten Record laden, Fact-ID zur Laufzeit ermitteln
        $record = Registry::gedcomRecordFactory()->make('X1030', $this->tree);
        self::assertNotNull($record, 'Demo-GEDCOM muss X1030 enthalten');

        /** @var Fact|null $editable_fact */
        $editable_fact = $record->facts()->first(static fn (Fact $f): bool => $f->canEdit());
        self::assertNotNull($editable_fact, 'X1030 muss mindestens einen editierbaren Fact haben');

        $handler = new EditFactPage(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X1030',
                'fact_id' => $editable_fact->id(),
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EditFactPage GET mit XREF-Form, aber im Baum nicht vorhandener XREF →
     * GedcomRecordFactory::make() liefert null → Auth::checkRecordAccess(null, true)
     * wirft HttpNotFoundException. Der Quell-Stub-Test ersetzt die Factory per
     * Registry-Override; hier wird stattdessen der echte „Record fehlt"-Pfad
     * gegen MySQL geprüft.
     *
     * @group ported-l2-doubles
     */
    public function test_edit_fact_page_throws_not_found_for_unknown_record(): void
    {
        // Arrange
        $handler = new EditFactPage(
            Registry::container()->get(GedcomEditService::class),
        );

        $request = $this->createRequest(
            attributes: [
                'tree'    => $this->tree,
                'xref'    => 'X999',
                'fact_id' => 'fact-123',
            ],
        );

        // Assert (Exception-Erwartung vor Act)
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }

    /**
     * SelectNewFact POST: Handler erzeugt einen Redirect (302) auf die AddNewFact-Route.
     * Eingaben: tree-Attribut, xref-Attribut (XREF-Form), fact aus dem POST-Body.
     * Die Quell-Stub-Datei legt einen Tree per TreeService::create() an und prüft
     * lediglich den Statuscode 302. Hier wird der reale Pfad gegen MySQL geprüft;
     * zusätzlich wird der Location-Header inhaltlich auf die AddNewFact-Route
     * verifiziert (tree-Name, XREF, Fact-Tag).
     *
     * @group ported-l2-doubles
     */
    public function test_select_new_fact_redirects_to_add_new_fact(): void
    {
        // Arrange
        $handler = new SelectNewFact();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['fact' => 'BIRT'],
            attributes: [
                'tree' => $this->tree,
                'xref' => 'X1030',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: 302 Redirect zur AddNewFact-Route (Pfad enthält tree-Name, XREF und Fact-Tag)
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        self::assertStringContainsString($this->tree->name(), $location);
        self::assertStringContainsString('X1030', $location);
        self::assertStringContainsString('BIRT', $location);
    }
}
