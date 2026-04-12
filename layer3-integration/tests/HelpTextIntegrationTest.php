<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\HelpText;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Komponentenintegrationstest: HelpText Request-Handler.
 *
 * Alle 12 bekannten Topic-IDs + Fehlerbehandlung für unbekannte Topics.
 *
 * @see docs/tds_conditions_ref.md S50
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\HelpText
 */
class HelpTextIntegrationTest extends MysqlTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validTopics(): array
    {
        return [
            'DATE'                 => ['DATE'],
            'NAME'                 => ['NAME'],
            'SURN'                 => ['SURN'],
            'OBJE'                 => ['OBJE'],
            'PLAC'                 => ['PLAC'],
            'RESN'                 => ['RESN'],
            'ROMN'                 => ['ROMN'],
            '_HEB'                 => ['_HEB'],
            'data-fixes'           => ['data-fixes'],
            'edit_SOUR_EVEN'       => ['edit_SOUR_EVEN'],
            'pending_changes'      => ['pending_changes'],
            'relationship-privacy' => ['relationship-privacy'],
        ];
    }

    /**
     * Alle 12 bekannten Hilfetext-Topic-IDs geben HTTP 200 zurück (EP1–EP5).
     */
    #[DataProvider('validTopics')]
    public function test_all_valid_topics_return_200(string $topic): void
    {
        $handler  = new HelpText();
        $request  = $this->createRequest(attributes: ['topic' => $topic]);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertNotEmpty((string) $response->getBody());
    }

    /**
     * Unbekannte Topic-ID liefert HTTP 200 mit generischem Hilfetext (EP6).
     * Der default-Case gibt immer 200 zurück — kein 404.
     */
    public function test_unknown_topic_returns_200_with_generic_message(): void
    {
        $handler  = new HelpText();
        $request  = $this->createRequest(attributes: ['topic' => 'nonexistent_topic_xyz']);
        $response = $handler->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertStringContainsString('not been written', (string) $response->getBody());
    }
}
