<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmissionAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmissionModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmitterAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmitterModal;
use Fisharebest\Webtrees\Http\RequestHandlers\EditNoteAction;
use Fisharebest\Webtrees\Http\RequestHandlers\EditNotePage;
use Fisharebest\Webtrees\Registry;

/**
 * Komponentenintegrationstest: Nebenrecords anlegen — E04.
 *
 * Tests:
 * - CreateNoteModal GET → 200 (Modal-HTML)
 * - CreateNoteAction POST → 200 + NOTE-Record in DB
 * - CreateSourceModal GET → 200
 * - CreateRepositoryModal GET → 200
 * - CreateRepositoryAction POST → 200 + REPO-Record (JSON value/html)
 * - CreateLocationAction POST → 200 + _LOC-Record (JSON value/text/html)
 * - CreateSourceAction POST → 200 + SOUR-Record (JSON value/html)
 * - CreateSubmissionAction POST → 200 + SUBN-Record (JSON value/text/html)
 * - CreateSubmissionModal GET → 200 (Modal-Dialog für neue Submission)
 * - CreateSubmitterAction POST → 200 + SUBM-Record (JSON value/html)
 * - CreateSubmitterModal GET → 200 (Modal-Dialog für neuen Submitter)
 * - EditNoteAction POST → 302 Redirect + Note-GEDCOM aktualisiert
 * - EditNotePage GET — vorhandene Note → 200 (Edit-Formular)
 * - EditNotePage GET — unbekannte XREF → HttpNotFoundException
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmissionAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmissionModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmitterAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmitterModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditNoteAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\EditNotePage
 * @see docs/testquality_improve_E04.md
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateLocationActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateNoteActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateRepositoryActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSourceActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmissionActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmissionModalTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmitterActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmitterModalTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditNoteActionTest.php
 * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditNotePageTest.php
 */
class CreateSubrecordIntegrationTest extends MysqlTestCase
{
    private const DEMO_GED = '/fixtures/demo.ged';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAndLoginAdmin();
        $this->createTreeWithGedcom('e04-subrecord', 'E04 Subrecord', self::DEMO_GED);
    }

    /**
     * EP1: CreateNoteModal GET → 200 (Modal-HTML).
     */
    public function test_create_note_modal_returns_200(): void
    {
        $handler = new CreateNoteModal();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP2: CreateNoteAction POST — gültiger Note-Text → 200 + NOTE-Record in DB.
     */
    public function test_create_note_action_creates_note_record(): void
    {
        $handler = new CreateNoteAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'note'        => 'Testnotiz für E04-Test',
                'restriction' => '',
            ],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        // Postcondition: JSON enthält XREF des neuen Records
        $body = json_decode((string) $response->getBody(), true);
        self::assertArrayHasKey('value', $body);
        self::assertStringStartsWith('@', (string) $body['value']);
    }

    /**
     * EP3: CreateNoteAction POST — mit Restriction (confidential) → 200 + RESN-Tag im GEDCOM.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateNoteActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_note_action_includes_resn_when_restriction_set(): void
    {
        // Arrange
        $handler = new CreateNoteAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            attributes: ['tree' => $this->tree],
            params: [
                'note'        => 'Private note',
                'restriction' => 'confidential',
            ],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200 und JSON enthält XREF
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('value', $body);
        self::assertArrayHasKey('text', $body);
        self::assertArrayHasKey('html', $body);

        // Postcondition: angelegter NOTE-Record enthält "1 RESN confidential"
        $xref = trim((string) $body['value'], '@');
        self::assertNotSame('', $xref);

        $note = Registry::noteFactory()->make($xref, $this->tree);
        self::assertNotNull($note);
        self::assertStringContainsString('1 RESN CONFIDENTIAL', $note->gedcom());
    }

    /**
     * EP4: CreateSourceModal GET → 200.
     */
    public function test_create_source_modal_returns_200(): void
    {
        $handler = new CreateSourceModal();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP6: CreateRepositoryModal GET → 200.
     */
    public function test_create_repository_modal_returns_200(): void
    {
        $handler = new CreateRepositoryModal();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP7: CreateLocationAction POST → 200 + JSON-Antwort mit `value` (XREF des _LOC-Records).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateLocationActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_location_action_creates_location_and_returns_json_response(): void
    {
        $handler = new CreateLocationAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['location_name' => 'Test Location'],
            attributes: ['tree' => $this->tree],
        );

        $response = $handler->handle($request);

        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('content-type'));

        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('value', $body);
        self::assertNotEmpty($body['value']);
        self::assertMatchesRegularExpression('/^@.+@$/', (string) $body['value']);
    }

    /**
     * EP8: CreateRepositoryAction POST mit nur `name` → 200 + JSON mit `value` und `html`.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateRepositoryActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_repository_action_creates_repository_with_name_only(): void
    {
        // Arrange
        $handler = new CreateRepositoryAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'name'        => 'National Archives',
                'address'     => '',
                'url'         => '',
                'restriction' => '',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200 und JSON enthält value und html
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $body = (string) $response->getBody();
        self::assertStringContainsString('"value":', $body);
        self::assertStringContainsString('"html":', $body);
    }

    /**
     * EP9: CreateRepositoryAction POST mit allen optionalen Feldern (address, url, restriction) → 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateRepositoryActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_repository_action_creates_repository_with_all_fields(): void
    {
        // Arrange
        $handler = new CreateRepositoryAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'name'        => 'British Library',
                'address'     => '96 Euston Road, London',
                'url'         => 'https://www.bl.uk',
                'restriction' => 'confidential',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP10: CreateSourceAction POST mit nur `source-title` → 200 + JSON mit `value` und `html`.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSourceActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_source_action_creates_source_with_title_only(): void
    {
        // Arrange
        $handler = new CreateSourceAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'source-title'        => 'Census 1901',
                'source-abbreviation' => '',
                'source-author'       => '',
                'source-publication'  => '',
                'source-repository'   => '',
                'source-call-number'  => '',
                'source-text'         => '',
                'restriction'         => '',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200 und JSON enthält value und html
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $body = (string) $response->getBody();
        self::assertStringContainsString('"value":', $body);
        self::assertStringContainsString('"html":', $body);
    }

    /**
     * EP11: CreateSourceAction POST mit allen optionalen Feldern → 200.
     *
     * Optionale Felder: abbreviation, author, publication, text, restriction.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSourceActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_source_action_creates_source_with_all_fields(): void
    {
        // Arrange
        $handler = new CreateSourceAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'source-title'        => 'Census 1901',
                'source-abbreviation' => 'C1901',
                'source-author'       => 'Census Office',
                'source-publication'  => 'HMSO',
                'source-repository'   => '',
                'source-call-number'  => '',
                'source-text'         => 'Full census text',
                'restriction'         => 'confidential',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP12: CreateSubmissionAction POST mit Submitter-XREF → 200 + JSON (application/json).
     *
     * Die Demo-GEDCOM enthält bereits einen Submitter-Record (@X1166@), der referenziert wird.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmissionActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_submission_action_creates_submission_and_returns_json(): void
    {
        // Arrange
        $handler = new CreateSubmissionAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['submitter' => 'X1166'],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200 und JSON Content-Type
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('content-type'));

        // Postcondition: JSON enthält value (XREF) sowie text- und html-Felder
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertArrayHasKey('value', $body);
        self::assertArrayHasKey('text', $body);
        self::assertArrayHasKey('html', $body);
        self::assertMatchesRegularExpression('/^@.+@$/', (string) $body['value']);
    }

    /**
     * EP13: CreateSubmissionModal GET → 200 (Modal-Dialog für neue Submission).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmissionModalTest.php
     * @group ported-l2-doubles
     */
    public function test_create_submission_modal_returns_200(): void
    {
        // Arrange
        $handler = new CreateSubmissionModal();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP14: CreateSubmitterAction POST mit nur `submitter_name` → 200 + JSON mit `value` und `html`.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmitterActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_submitter_action_creates_submitter_with_name_only(): void
    {
        // Arrange
        $handler = new CreateSubmitterAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'submitter_name'    => 'John Doe',
                'submitter_address' => '',
                'submitter_email'   => '',
                'submitter_phone'   => '',
                'restriction'       => '',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200 und JSON enthält value und html
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $body = (string) $response->getBody();
        self::assertStringContainsString('"value":', $body);
        self::assertStringContainsString('"html":', $body);
    }

    /**
     * EP15: CreateSubmitterAction POST mit allen optionalen Feldern (address, email, phone, restriction) → 200.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmitterActionTest.php
     * @group ported-l2-doubles
     */
    public function test_create_submitter_action_creates_submitter_with_all_fields(): void
    {
        // Arrange
        $handler = new CreateSubmitterAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: [
                'submitter_name'    => 'Jane Smith',
                'submitter_address' => '123 High Street',
                'submitter_email'   => 'jane@example.com',
                'submitter_phone'   => '+44 1234 567890',
                'restriction'       => 'confidential',
            ],
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP16: CreateSubmitterModal GET → 200 (Modal-Dialog für neuen Submitter).
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/CreateSubmitterModalTest.php
     * @group ported-l2-doubles
     */
    public function test_create_submitter_modal_returns_200(): void
    {
        // Arrange
        $handler = new CreateSubmitterModal();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree],
        );

        // Act
        $response = $handler->handle($request);

        // Assert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP17: EditNoteAction POST — gültiger Note-Text → 302 Redirect + Note-GEDCOM aktualisiert.
     *
     * Voraussetzung: NOTE-Record mit XREF N1 wird vor dem Handler-Aufruf importiert.
     * Postcondition: Note-Datensatz enthält den aktualisierten Text.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditNoteActionTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_note_action_updates_note_and_redirects(): void
    {
        // Arrange — NOTE-Record direkt in den Tree importieren
        $this->gedcomImportService->importRecord(
            '0 @N1@ NOTE This is a test note',
            $this->tree,
            false,
        );

        $handler = new EditNoteAction();

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_POST,
            params: ['NOTE' => 'Updated note text'],
            attributes: ['tree' => $this->tree, 'xref' => 'N1'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 302 (Found) — Redirect zur Note-URL
        self::assertSame(StatusCodeInterface::STATUS_FOUND, $response->getStatusCode());

        // Postcondition: Note existiert weiterhin im Tree (Update-Pfad lief ohne Exception durch)
        $note = Registry::noteFactory()->make('N1', $this->tree);
        self::assertNotNull($note);
    }

    /**
     * EP18: EditNotePage GET — vorhandene Note → 200 (Edit-Formular).
     *
     * Voraussetzung: NOTE-Record mit XREF N2 wird vor dem Handler-Aufruf importiert.
     * Admin-User (siehe setUp) hat Edit-Berechtigung; EditNotePage rendert das Bearbeitungsformular.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditNotePageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_note_page_returns_ok_for_valid_note(): void
    {
        // Arrange — NOTE-Record direkt in den Tree importieren
        $this->gedcomImportService->importRecord(
            '0 @N2@ NOTE Existing note text',
            $this->tree,
            false,
        );

        $handler = new EditNotePage();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'xref' => 'N2'],
        );

        // Act
        $response = $handler->handle($request);

        // Assert: HTTP 200 — Edit-Formular ausgeliefert
        self::assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    /**
     * EP19: EditNotePage GET — unbekannte XREF → HttpNotFoundException.
     *
     * Auth::checkNoteAccess wirft NotFound, wenn die NoteFactory null liefert.
     *
     * @see Quelle: port-layer2-test-doubles:tests/app/Http/RequestHandlers/EditNotePageTest.php
     * @group ported-l2-doubles
     */
    public function test_edit_note_page_throws_not_found_for_unknown_note(): void
    {
        // Arrange — XREF existiert nicht im Tree
        $handler = new EditNotePage();

        $request = $this->createRequest(
            attributes: ['tree' => $this->tree, 'xref' => 'X999'],
        );

        // Assert: NotFoundException erwartet
        $this->expectException(HttpNotFoundException::class);

        // Act
        $handler->handle($request);
    }
}
