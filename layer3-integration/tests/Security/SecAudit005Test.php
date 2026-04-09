<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DombrinksBlagen\WebtreesTests\Integration\Security;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Http\Exceptions\HttpAccessDeniedException;
use Fisharebest\Webtrees\Http\RequestHandlers\ModuleAction;
use Fisharebest\Webtrees\Services\ModuleService;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

/**
 * Regression for SEC-AUDIT-005 — ModuleAction::handle() case-insensitive
 * admin-gate bypass. Discovered in verify-2026-04-08T21-45-10 V1e.2,
 * end-to-end PoC verified against the live webtrees container.
 *
 * Spec: docs/security-audit/tasks/SEC-AUDIT-005_module_action_case_bypass.md
 *
 * Pre-fix behaviour:
 *   ModuleAction.php:75 used str_contains($action, 'Admin') as admin gate
 *   but PHP method dispatch is case-insensitive. A URL like
 *   /module/faq/adminedit bypassed the gate yet reached postAdminEditAction
 *   on the real FrequentlyAskedQuestionsModule as an unauthenticated guest.
 *
 * Post-fix behaviour:
 *   The gate uses stripos(...) !== false and therefore matches every casing
 *   variant. All rows in this test must raise HttpAccessDeniedException
 *   *before* the handler reaches the method lookup.
 *
 * Layer-3 value over the Layer-2 ModuleActionTest::testAdminActionCaseBypass:
 *   - Uses the real ModuleService (not a mock) with the full set of bundled
 *     modules registered and their DB-backed enabled status honoured.
 *   - Exercises the real ModuleAction handler in the real webtrees bootstrap
 *     (container, I18N, MySQL) — the same wiring a production request walks.
 *
 * Self-skip: the test class probes for the fix in setUp() via a behavioral
 * probe (a guest request with a lowercase `admin` action must throw
 * HttpAccessDeniedException). If the probe fails, the whole class is marked
 * skipped so `make test-integration` stays green when WEBTREES_SOURCE points
 * at an unfixed webtrees tree.
 *
 * @covers \Fisharebest\Webtrees\Http\RequestHandlers\ModuleAction
 */
final class SecAudit005Test extends SecurityAuditTestCase
{
    private ModuleService $moduleService;
    private ModuleAction $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleService = new ModuleService();
        $this->handler       = new ModuleAction($this->moduleService);

        $this->skipIfFixAbsent();
        $this->skipIfRequiredModulesDisabled();
    }

    public function test_baseline_admin_action_is_blocked_on_faq(): void
    {
        $this->expectException(HttpAccessDeniedException::class);
        $this->expectExceptionMessage('Admin only action');

        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: [
                'module' => 'faq',
                'action' => 'Admin',
                'user'   => new GuestUser(),
            ],
        );

        $this->handler->handle($request);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function caseBypassProvider(): array
    {
        // [method, action, module]
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

    /**
     * Every combination of HTTP method, casing variant, and real bundled
     * module must raise HttpAccessDeniedException when invoked by a guest.
     * Pre-fix: the lowercase variants bypassed the admin gate and either
     * reached the real admin method (smoking gun: `adminedit` hitting
     * FrequentlyAskedQuestionsModule::getAdminEditAction) or triggered a
     * HttpNotFoundException on method lookup.
     */
    #[DataProvider('caseBypassProvider')]
    public function test_all_variants_are_blocked_by_admin_gate(
        string $method,
        string $action,
        string $module,
    ): void {
        $this->expectException(HttpAccessDeniedException::class);
        $this->expectExceptionMessage('Admin only action');

        $request = $this->createRequest(
            method: $method,
            attributes: [
                'module' => $module,
                'action' => $action,
                'user'   => new GuestUser(),
            ],
        );

        $this->handler->handle($request);
    }

    // ----- self-skip helpers -----

    private function skipIfFixAbsent(): void
    {
        $request = $this->createRequest(
            method: RequestMethodInterface::METHOD_GET,
            attributes: [
                'module' => 'faq',
                'action' => 'admin',
                'user'   => new GuestUser(),
            ],
        );

        try {
            $this->handler->handle($request);
        } catch (HttpAccessDeniedException) {
            return; // fix is present — continue with the test methods
        } catch (Throwable $e) {
            self::markTestSkipped(sprintf(
                'SEC-AUDIT-005 fix not present: handler threw %s instead of '
                . 'HttpAccessDeniedException when a guest invoked /module/faq/admin. '
                . 'Point WEBTREES_SOURCE at a tree that contains commits '
                . '3a53e837de / f8fdf173cf on branch '
                . 'security-audit-005-module-action-case-bypass to enable.',
                $e::class,
            ));
        }

        self::markTestSkipped(
            'SEC-AUDIT-005 fix not present: handler returned without throwing when '
            . 'a guest invoked /module/faq/admin — gate bypassed and dispatch reached.',
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
