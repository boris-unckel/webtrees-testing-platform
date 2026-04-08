// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Security-Audit-Helper für Layer 4 (Playwright).
//
// Gegenstück zu SecurityAuditTestCase aus Layer 3.
// Spec: docs/security-audit/08_layer_integration.md §3.3
//
// Aktivierung: ENV `WEBTREES_SECURITY_TRACE=1` muss am webtrees-Container gesetzt
// sein (in compose.yaml vorbereitet). Ohne Header `X-Audit-Probe` schreibt die
// Middleware nichts — der Helper setzt den Header pro Test.

import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Wurzel-Verzeichnis der SecurityTrace-Artefakte **im Playwright-Container**.
 *
 * Der Playwright-Container mountet `./artifacts:/artifacts:rw,z` (siehe compose.yaml),
 * sodass die Middleware-Outputs unter `/artifacts/security-trace/` direkt lesbar sind.
 */
const ARTIFACT_ROOT = '/artifacts/security-trace';

/**
 * Leitet die Task-ID aus einer Hypothese-ID ab.
 *
 * `SEC-AUDIT-042-H1` → `SEC-AUDIT-042`
 */
function taskIdFromHypothesis(hypothesisId: string): string {
    const m = hypothesisId.match(/^(SEC-AUDIT-\d+)/);
    if (!m) {
        throw new Error(
            `Hypothese-ID "${hypothesisId}" folgt nicht dem Muster SEC-AUDIT-<NNN>[-Suffix].`,
        );
    }
    return m[1];
}

/**
 * Hängt den `X-Audit-Probe`-Header an **alle** webtrees-Requests der Page.
 *
 * Muss vor dem ersten `page.goto(...)` aufgerufen werden. Setzt eine `page.route()`-
 * Regel, die für jeden matchenden Request den Header injiziert.
 */
export async function attachProbeHeader(page: Page, hypothesisId: string): Promise<void> {
    await page.route(/^http:\/\/webtrees(:\d+)?\//, async (route) => {
        await route.continue({
            headers: {
                ...route.request().headers(),
                'x-audit-probe': hypothesisId,
            },
        });
    });
}

/**
 * Listet alle Artefakt-Dateien einer Task-ID, die die Hypothese-ID enthalten.
 */
function artifactFiles(hypothesisId: string): string[] {
    const taskId = taskIdFromHypothesis(hypothesisId);
    const dir = path.join(ARTIFACT_ROOT, taskId);
    if (!fs.existsSync(dir)) {
        return [];
    }
    const all = fs
        .readdirSync(dir)
        .filter((f) => f.endsWith('.json'))
        .map((f) => path.join(dir, f));

    const matching: string[] = [];
    for (const f of all) {
        try {
            const content = fs.readFileSync(f, 'utf8');
            if (content.includes(hypothesisId)) {
                matching.push(f);
            }
        } catch {
            // Datei konnte nicht gelesen werden (Race condition?) — überspringen.
        }
    }
    return matching.sort();
}

/**
 * Liest das jüngste Trace-Artefakt für die Hypothese und parst es als JSON.
 *
 * Gibt `null` zurück, wenn kein Artefakt gefunden wurde.
 */
export function readTraceArtifact(hypothesisId: string): Record<string, unknown> | null {
    const files = artifactFiles(hypothesisId);
    if (files.length === 0) {
        return null;
    }
    const latest = files[files.length - 1];
    const content = fs.readFileSync(latest, 'utf8');
    try {
        return JSON.parse(content);
    } catch {
        return null;
    }
}

/**
 * Assertion: Das Trace-Artefakt enthält den erwarteten Branch-Marker.
 *
 * Für Pre-Fix-Reproduktion: bestätigt, dass der Exploit-Pfad durchlaufen wurde.
 */
export function expectTraceHit(hypothesisId: string, expectedBranch: string): void {
    const artifact = readTraceArtifact(hypothesisId);
    expect(artifact, `Kein Trace-Artefakt für ${hypothesisId} gefunden.`).not.toBeNull();
    const haystack = JSON.stringify(artifact);
    expect(
        haystack,
        `Trace enthält erwarteten Branch ${expectedBranch} nicht.`,
    ).toContain(expectedBranch);
}

/**
 * Gegen-Assertion: Das Trace-Artefakt darf ein bestimmtes Feld **nicht** enthalten.
 *
 * Für Post-Fix-Regression: der Sink wurde nicht mehr erreicht.
 */
export function expectTraceAbsent(hypothesisId: string, forbiddenField: string): void {
    const artifact = readTraceArtifact(hypothesisId);
    if (artifact === null) {
        // Kein Artefakt → Feld trivial abwesend.
        return;
    }
    const haystack = JSON.stringify(artifact);
    expect(
        haystack,
        `Trace enthält Feld ${forbiddenField}, das nach Fix nicht mehr auftauchen darf.`,
    ).not.toContain(forbiddenField);
}

/**
 * Stärkste Form: Es existiert **kein** Artefakt für diese Hypothese.
 *
 * Bedeutung: Der Request wurde durch einen Schutz-Mechanismus abgefangen,
 * **bevor** die Middleware-Chain den Ziel-Handler erreicht hat.
 */
export function expectNoTraceArtifact(hypothesisId: string): void {
    const files = artifactFiles(hypothesisId);
    expect(
        files.length,
        `Erwartet: keine Trace-Artefakte für ${hypothesisId}, gefunden: ${files.length}`,
    ).toBe(0);
}

/**
 * Räumt alle Artefakte einer Task-ID auf (Gegenstück zu `resetSecurityTraceArtifacts`).
 *
 * Kann in `test.afterEach(...)` verwendet werden, um Cross-Test-Kontamination zu vermeiden.
 */
export function resetSecurityTraceArtifacts(hypothesisId: string): void {
    const taskId = taskIdFromHypothesis(hypothesisId);
    const dir = path.join(ARTIFACT_ROOT, taskId);
    if (!fs.existsSync(dir)) {
        return;
    }
    for (const f of fs.readdirSync(dir)) {
        if (f.endsWith('.json') || f.endsWith('.json.tmp')) {
            try {
                fs.unlinkSync(path.join(dir, f));
            } catch {
                // Best-effort cleanup.
            }
        }
    }
    try {
        fs.rmdirSync(dir);
    } catch {
        // Directory nicht leer oder noch in Benutzung — ignorieren.
    }
}
