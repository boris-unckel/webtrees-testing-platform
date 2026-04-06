<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryModal;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceModal;

/**
 * Komponentenintegrationstest: Nebenrecords anlegen — E04.
 *
 * Tests:
 * - CreateNoteModal GET → 200 (Modal-HTML)
 * - CreateNoteAction POST → 200 + NOTE-Record in DB
 * - CreateSourceModal GET → 200
 * - CreateRepositoryModal GET → 200
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteAction
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceModal
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryModal
 * @see docs/testquality_improve_E04.md
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
}
