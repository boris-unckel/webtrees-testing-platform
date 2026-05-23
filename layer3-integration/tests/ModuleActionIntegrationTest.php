<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\RequestHandlers\ModuleAction;
use Fisharebest\Webtrees\Services\ModuleService;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

/**
 * Komponentenintegrationstest: ModuleAction Runtime-Dispatch.
 *
 * SEC-AUDIT-005 Regressionsabdeckung (verhaltens-definitiv):
 *   Der Admin-Gate in ModuleAction::handle() darf nicht über Case-Varianten
 *   im Action-Parameter umgangen werden. Asserts auf das Property
 *   "Guest wird abgewiesen" — entweder via HttpAccessDeniedException
 *   (geworfen) oder HTTP 403 (Response).
 *
 * Pre-fix: ModuleAction nutzte str_contains($action, 'Admin'), PHP-Methoden-
 * Dispatch ist aber case-insensitiv. Smoking-Gun:
 *   /module/faq/adminedit  → reachte postAdminEditAction auf
 *                            FrequentlyAskedQuestionsModule als Guest.
 *
 * Fixture-Voraussetzung: die gebündelten Module `faq`, `stories` und
 * `relationships_chart` müssen in der Test-DB aktiv sein. Dies ist eine
 * Fixture-Bedingung (keine Patch-Bedingung) — wird per markTestSkipped
 * gehandhabt, falls eine andere Test-Fixture die Module deaktiviert hat.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModuleAction
 * @see docs/security-audit/tasks/SEC-AUDIT-005_module_action_case_bypass.md
 */
final class ModuleActionIntegrationTest extends MysqlTestCase
{
    private ModuleService $moduleService;
    private ModuleAction $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleService = new ModuleService();
        $this->handler       = new ModuleAction($this->moduleService);

        $this->skipIfRequiredModulesDisabled();
    }

    public function test_sec_audit_005_baseline_admin_action_blocked_on_faq(): void
    {
        $this->assertModuleActionBlockedForGuest(
            RequestMethodInterface::METHOD_GET,
            'Admin',
            'faq',
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function caseBypassProvider(): array
    {
        return [
            'GET faq admin'                    => [RequestMethodInterface::METHOD_GET,  'admin',       'faq'],
            'GET faq ADMIN'                    => [RequestMethodInterface::METHOD_GET,  'ADMIN',       'faq'],
            'GET faq AdMiN'                    => [RequestMethodInterface::METHOD_GET,  'AdMiN',       'faq'],
            'GET faq adminedit (smoking gun)'  => [RequestMethodInterface::METHOD_GET,  'adminedit',   'faq'],
            'GET faq admin-edit'               => [RequestMethodInterface::METHOD_GET,  'admin-edit',  'faq'],
            'POST faq admindelete'             => [RequestMethodInterface::METHOD_POST, 'admindelete', 'faq'],
            'POST faq AdminDelete baseline'    => [RequestMethodInterface::METHOD_POST, 'AdminDelete', 'faq'],
            'POST stories admindelete'         => [RequestMethodInterface::METHOD_POST, 'admindelete', 'stories'],
            'POST relationships_chart admin'   => [RequestMethodInterface::METHOD_POST, 'admin',       'relationships_chart'],
        ];
    }

    #[DataProvider('caseBypassProvider')]
    public function test_sec_audit_005_all_variants_blocked_by_admin_gate(
        string $method,
        string $action,
        string $module,
    ): void {
        $this->assertModuleActionBlockedForGuest($method, $action, $module);
    }

    private function assertModuleActionBlockedForGuest(string $method, string $action, string $module): void
    {
        $request = $this->createRequest(
            method:     $method,
            attributes: [
                'module' => $module,
                'action' => $action,
                'user'   => new GuestUser(),
            ],
        );

        try {
            $response = $this->handler->handle($request);
        } catch (HttpAccessDeniedException) {
            self::assertTrue(true, "Block via HttpAccessDeniedException for {$method} /module/{$module}/{$action}");
            return;
        } catch (Throwable $e) {
            self::fail(sprintf(
                'Unexpected exception %s instead of denial for %s /module/%s/%s: %s',
                $e::class,
                $method,
                $module,
                $action,
                $e->getMessage(),
            ));
        }

        self::assertSame(
            403,
            $response->getStatusCode(),
            sprintf(
                'Guest invoking %s /module/%s/%s must be denied (HTTP 403 or HttpAccessDeniedException)',
                $method,
                $module,
                $action,
            ),
        );
    }

    private function skipIfRequiredModulesDisabled(): void
    {
        foreach (['faq', 'stories', 'relationships_chart'] as $name) {
            if ($this->moduleService->findByName($name) === null) {
                self::markTestSkipped(sprintf(
                    'SEC-AUDIT-005 regression requires bundled module %s to be '
                    . 'enabled in the test database. Seed the test fixture with '
                    . 'the default module enablement before running this test.',
                    $name,
                ));
            }
        }
    }
}
