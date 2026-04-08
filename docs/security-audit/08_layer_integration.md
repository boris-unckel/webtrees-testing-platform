<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 08 — Layer-Integration (Regressions- und Exploit-Tests)

**Zweck:** Regelwerk, wie ein bestätigter Security-Audit-Befund (`SEC-AUDIT-<NNN>`) als dauerhafter Regressionstest in die bestehende Layer-Architektur eingebettet wird — ohne Parallel-Infrastruktur aufzumachen und ohne die bestehende Test-Taxonomie zu zerfasern.

**Vorgänger:** `prompt_02_whitebox_deep_dive.md` spezifiziert die Regression-Coverage-Spec, `prompt_05_validation.md` validiert am Ende gegen die hier beschriebenen Klassen.

## 1. Layer-Zuordnung

Die Audit-Pipeline nutzt **ausschließlich Layer 3 und Layer 4**. Layer 2 (Upstream-Unit-Tests) bleibt unberührt — dort werden keine Audit-Regressionen abgelegt, damit `make test-unit` (SQLite in-memory) unabhängig bleibt und weiterhin den Upstream-Baseline spiegelt.

| Kriterium der Hypothese | Ziel-Layer |
|---|---|
| Einzelne Middleware/Handler, keine DOM-Interaktion, HTTP-Status + DB-State reichen als Oracle | **Layer 3** (`layer3-integration/tests/Security/`) |
| Mehrere Requests über Session hinweg, Cookie-Handling, Browser-Quirk relevant, JavaScript involviert | **Layer 4** (`layer4-e2e/tests/security-audit/`) |
| Upload-Kette mit Content-Inspection nach Upload (Trace zeigt File-I/O) | **Layer 3**, solange das File-Handling synchron im PHP-Prozess passiert; sonst Layer 4 |
| Visitor-Sandbox-Escape mit PHP-Seitenpfad nur HTTP-basiert prüfbar | **Layer 3** |
| Visitor-Sandbox-Escape, der einen Boomerang-RUM-Trigger oder Browser-Cache-Exploit braucht | **Layer 4** |

**Faustregel:** Layer 3 ist der Default. Layer 4 nur, wenn die Hypothese **nicht** ohne Browser abbildbar ist. Layer 3 ist deterministisch, schnell und isoliert pro Testklasse.

## 2. Layer-3 — `SecurityAuditTestCase`

### 2.1 Ablage

- Verzeichnis: `layer3-integration/tests/Security/`
- Basisklasse: `layer3-integration/tests/Security/SecurityAuditTestCase.php`
- Namespace: `DombrinksBlagen\WebtreesTests\Integration\Security`

`SecurityAuditTestCase` **erweitert** `MysqlTestCase` (aus `layer3-integration/tests/MysqlTestCase.php`) und fügt Helfer hinzu, die für Audit-Regressionen typisch sind. Keine Änderung an `MysqlTestCase` selbst — das bleibt die generische Integrations-Basis.

### 2.2 Verantwortlichkeiten von `SecurityAuditTestCase`

| Helfer | Zweck |
|---|---|
| `sendProbeRequest(ServerRequestInterface $request, string $hypothesisId): ResponseInterface` | Wrappt einen PSR-7-Request, fügt `X-Audit-Probe: SEC-AUDIT-<NNN>-<Hn>` Header hinzu, ruft die webtrees-Request-Handler-Pipeline auf, gibt die Response zurück. Trace-Artefakt landet im gleichen Pfadmuster wie bei echten Probes (`05_security_trace_middleware.md` §4). |
| `loadFixture(string $name): array` | Lädt JSON aus `fixtures/security/payloads/<name>.json` und gibt das decodierte Array zurück. Registriert die Quelle in einem `coversNothing`-freien Kommentar für Audit-Trail. |
| `assertResponseBlocked(ResponseInterface $response, int $expectedStatus = 403): void` | Erwartet einen abwehrenden Status (403/401/422). Failt ausdrücklich **nicht** nur auf Status 500 — das ist kein Block, sondern Crash. |
| `assertTraceHit(string $hypothesisId, string $expectedBranch): void` | Öffnet das Trace-Artefakt für `hypothesisId` und prüft, dass `security_audit.branches_taken` den erwarteten Branch enthält. Für **pre-fix**-Zustände (Exploit-Reproduktion). |
| `assertTraceAbsent(string $hypothesisId, string $forbiddenField): void` | Gegen-Assertion: das genannte Feld darf im Trace **nicht** vorkommen. Für **post-fix**-Zustände (Regression). |
| `assertNoSecurityTraceArtifact(string $hypothesisId): void` | Stärkste Form: Nach dem Request wurde überhaupt kein Audit-Trace erzeugt — der Request wurde früh durch eine Schutz-Middleware abgeschnitten (`middleware_short_circuit` aus `05_security_trace_middleware.md`). |
| `resetSecurityTraceArtifacts(): void` | Wird in `tearDown()` aufgerufen, löscht alle Trace-Dateien zum Hypothesis-ID-Prefix der aktuellen Testmethode. Keine Cross-Test-Kontamination. |

`SecurityAuditTestCase` **aktiviert** `SecurityTraceMiddleware` per `SetUp`: Setzen der Environment-Variable `WEBTREES_SECURITY_TRACE=1` im Testprozess und Registrierung der Middleware in der Container-Instanz. In `tearDown` wird beides zurückgesetzt, damit andere Testklassen (z. B. `BadBotBlockerIntegrationTest`) nicht ungewollt das Audit-Format sehen.

### 2.3 Naming-Konvention

- Testklasse: `SecAudit<NNN>Test.php` (eine Klasse pro Task, **nicht** pro Hypothese)
- Testmethoden: `test_h<n>_<kurz_snake_case>()` pro bestätigter Hypothese
- Beispiel: `layer3-integration/tests/Security/SecAudit042Test.php` mit `test_h1_gedcom_note_xss_via_smart_quotes()`, `test_h2_gedcom_note_xss_via_entity_decode()`

### 2.4 DataProvider-Muster

Jede Hypothese mit mehreren Payload-Varianten (Equivalence-Partitioning / BVA) nutzt einen PHPUnit-DataProvider, der aus einer Fixture-Datei lädt:

```php
#[DataProvider('providePayloadsH1')]
public function test_h1_gedcom_note_xss_via_smart_quotes(array $payload, bool $shouldBeBlocked): void
{
    $request = $this->buildGedcomNoteRequest($payload['note_raw']);
    $response = $this->sendProbeRequest($request, 'H1');

    if ($shouldBeBlocked) {
        $this->assertResponseBlocked($response);
        $this->assertNoSecurityTraceArtifact('H1');   // Post-Fix-Regression: Short-Circuit erwartet
    } else {
        // Legitime Variante: muss weiterhin funktionieren
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTraceAbsent('H1', 'security_audit.expected_sink_hit');
    }
}

public static function providePayloadsH1(): iterable
{
    $fixtures = json_decode(file_get_contents(__DIR__ . '/../../../fixtures/security/payloads/sec_audit_042.json'), true);
    foreach ($fixtures['H1'] as $case) {
        yield $case['name'] => [$case['payload'], $case['should_be_blocked']];
    }
}
```

**Strikt:** Jede Testmethode testet **eine** Hypothese. Keine verschachtelten if/else-Pfade, keine Testmethoden, die mehrere Hypothesen abdecken. Die Trennung ist für den Audit-Trail zwingend (Nachvollziehbarkeit in `validation.md` und `finding_report_template.md`).

### 2.5 Anbindung an `phpunit-integration.xml`

`layer3-integration/phpunit-integration.xml` braucht **keine** Änderung. Die `<directory>/tests/layer3-integration/tests</directory>`-Klausel erfasst auch `tests/Security/` rekursiv. Clover-Coverage läuft mit. Der einzige Unterschied: In einem Audit-Run setzt der Driver zusätzlich `WEBTREES_SECURITY_TRACE=1` über `--dotenv` oder direkt in der Container-Environment, damit die in §2.2 beschriebenen Helfer echte Trace-Artefakte sehen — in der regulären Full-Suite (`make test-integration`) reicht die In-Process-Aktivierung durch `SecurityAuditTestCase::setUp`.

### 2.6 Zugriff auf Fork-Branch (Doppelt-Run aus Validation D7)

`prompt_05_validation.md` erfordert zwei Testläufe derselben Klasse: einmal gegen den unpatched webtrees (Baseline) und einmal gegen den Fork-Branch (Fix wirkt). Beides läuft mit identischem PHPUnit-Aufruf, unterschieden nur durch die Environment-Variable `WEBTREES_SOURCE`:

```bash
# unpatched baseline
WEBTREES_SOURCE=./upstream/webtrees make test-integration-security-<NNN>

# patched fork
WEBTREES_SOURCE=/home/borisunckel/phpprojects/webtrees-upstream/webtrees make test-integration-security-<NNN>
```

**Neues Make-Target** (kein Breaking-Change am existierenden Test-Runner):

```makefile
test-integration-security-%:
	podman-compose exec webtrees vendor/bin/phpunit \
	    --configuration=/tests/layer3-integration/phpunit-integration.xml \
	    --filter='SecAudit$*Test'
```

Der Target-Name nimmt die Task-Nummer als Parameter (`make test-integration-security-042`). Coverage für Einzelklassen wird unterdrückt (Flag `--no-coverage` innerhalb des Targets), damit schnelle Iterations-Läufe möglich sind — die volle Coverage übernimmt weiterhin `make test-integration`.

## 3. Layer-4 — `layer4-e2e/tests/security-audit/`

### 3.1 Ablage

- Verzeichnis: `layer4-e2e/tests/security-audit/`
- Config: bestehende `layer4-e2e/playwright-security.config.ts` wird **wiederverwendet** (dort ist bereits der Fokus auf SEC-* Tests gesetzt, siehe existierende Specs in `layer4-e2e/tests/security/`).

**Unterschied zu `layer4-e2e/tests/security/`:**
- `tests/security/` = bestehende SEC-H01..SEC-HDR04-Features (aus `tds_conditions_ref.md`), vom User kuratiert.
- `tests/security-audit/` = durch die Audit-Pipeline generierte Regressionen aus `SEC-AUDIT-<NNN>`. Klar getrennt, damit Feature-Tests und Audit-Regressionen nicht vermischt werden.

### 3.2 Namenskonvention

- Spec-Datei: `sec-audit-<NNN>.spec.ts` (eine Datei pro Task)
- `test.describe('SEC-AUDIT-<NNN>')` Block pro Task
- `test('H<n>: <kurz>')` pro bestätigter Hypothese

### 3.3 Helfer-Modul

Neu: `layer4-e2e/helpers/security-audit.ts` mit Funktionen, die das Gegenstück zu `SecurityAuditTestCase` sind:

| Funktion | Zweck |
|---|---|
| `attachProbeHeader(request, hypothesisId)` | Playwright `page.route()`-Callback, der den `X-Audit-Probe`-Header an alle passenden Requests klebt. |
| `readTraceArtifact(hypothesisId)` | Liest das Audit-Trace-Artefakt aus dem Container (`podman-compose exec webtrees cat /artifacts/security-audit/traces/...`) und gibt das JSON zurück. |
| `expectTraceHit(hypothesisId, branch)` | Playwright-Assertion auf das Trace-Artefakt. |
| `expectTraceAbsent(hypothesisId, field)` | Gegen-Assertion. |
| `expectNoTraceArtifact(hypothesisId)` | Prüft, dass kein Artefakt erzeugt wurde (Short-Circuit). |

Diese Helfer bauen auf `layer4-e2e/helpers/otel-fixture.ts` auf — dort ist bereits das Muster etabliert, wie Playwright mit Container-seitigen Artefakten korreliert.

### 3.4 Page-Fixture-Muster

```typescript
// layer4-e2e/tests/security-audit/sec-audit-042.spec.ts
import { test, expect } from '@playwright/test';
import { attachProbeHeader, expectTraceAbsent, expectNoTraceArtifact } from '../../helpers/security-audit';

test.describe('SEC-AUDIT-042 — GEDCOM Note XSS', () => {
  test.beforeEach(async ({ page }) => {
    await attachProbeHeader(page, 'SEC-AUDIT-042-H1');
  });

  test('H1: smart quotes do not escape the note sanitizer', async ({ page }) => {
    await page.goto('/tree/xss-sandbox/note/N1/edit');
    await page.getByLabel('Note').fill(await import('../../../fixtures/security/payloads/sec_audit_042.json')
      .then(f => f.default.H1[0].payload.note_raw));
    await page.getByRole('button', { name: 'save' }).click();

    // Post-Fix-Regression: Response darf keine unescaped <script> enthalten
    await expect(page.locator('.note-display')).not.toContainText('<script>');
    await expectNoTraceArtifact('SEC-AUDIT-042-H1');
  });
});
```

Die Fixture-Datei ist **dieselbe** wie bei Layer 3 (`fixtures/security/payloads/sec_audit_042.json`) — siehe `09_fixture_register.md` für die Struktur. Kein Doppel-Pflegen von Payloads.

## 4. Fixture-Bindung

Jede `SecAudit<NNN>Test.php` und jede `sec-audit-<NNN>.spec.ts` liest Payloads **ausschließlich** aus `fixtures/security/payloads/sec_audit_<NNN>.json`. Keine Inline-Payloads in Test-Dateien. Begründung:

1. Wiederverwendbarkeit zwischen Layer 3 und Layer 4 ohne Copy-Paste.
2. Audit-Trail: der Driver trackt Fixture-Änderungen per Git-Hash in `tasks/SEC-AUDIT-<NNN>.md`.
3. `prompt_05_validation.md` P4 (Konsistenz-Prüfung) kann Probe-Spec gegen Fixture gegenprüfen — ohne Inline-Magie.

Details zur Fixture-Struktur: `09_fixture_register.md`.

## 5. Parallel-Safety

- **Innerhalb von `make test-integration`:** `SecAudit<NNN>Test`-Klassen laufen sequenziell wie alle anderen Layer-3-Tests (CLAUDE.md „Exklusive Ausführung"-Regel). Die `SecurityTraceMiddleware`-Aktivierung ist per Environment + Header-Guard geschützt, weshalb andere Klassen nicht versehentlich Trace-Artefakte produzieren.
- **Trace-Artefakt-Namespacing:** Jede Testmethode benutzt einen eindeutigen `hypothesis_id` (enthält Task-NNN und H-Nummer und einen Test-Scope-Suffix `-test`). `tearDown` räumt nur die eigene Scope-Suffix-Familie auf. Tests aus anderen Audit-Tasks bleiben unangetastet.
- **Keine gemeinsamen DB-Fixtures zwischen Audit-Klassen:** Jede Klasse erzeugt ihren eigenen Baum (`createTreeWithGedcom()` aus `MysqlTestCase`) mit einem testspezifischen Suffix, um Kollisionen zu vermeiden.

## 6. Abgrenzung zum Probe-Loop

`SecurityAuditTestCase` ist **ausschließlich** für Regression nach einem bestätigten Finding. Der **initiale Probe-Loop** aus `prompt_03_exploit_attempt.md` läuft nicht durch PHPUnit, sondern durch vom Driver extrahierte `run.sh`-Skripte im Container. Grund:

- Der Probe-Loop braucht schnelle, flache Iteration (minimale Test-Bootstrap-Kosten).
- PHPUnit-Bootstrap lädt MySQL + webtrees-Runtime, was für einen Container-HTTP-Probe unnötiger Overhead wäre.
- Nach Confirmation wird der erfolgreiche Probe **manuell vom LLM** in eine `SecAudit<NNN>Test`-Methode übersetzt (Phase D5 in `06_agentic_loop_driver.md`) — das ist die Rolle der Regression-Coverage-Spec aus `prompt_02_whitebox_deep_dive.md` §3.

## 7. Integration in bestehende Workflows

- `make test-integration` führt auch neue `SecAudit<NNN>Test`-Klassen aus, ohne spezielle Flags.
- `make crap-report` (aus CLAUDE.md) berücksichtigt die neuen Testklassen automatisch, da sie unter `source/app/` liegende Dateien abdecken — die Audit-Tasks sinken im CRAP-Ranking sichtbar ab, sobald die Regression Coverage hinzufügt.
- `make test-e2e` und `make test-e2e-quick` erfassen `layer4-e2e/tests/security-audit/` mit, da die `playwright-security.config.ts` alle Specs im `tests/`-Baum einschließt. Falls hier ausdrückliche Filterung nötig wird (um Audit-Specs in CI zu überspringen), kann der User den Config-Pfad anpassen — nicht Aufgabe des Audit-Drivers.

## 8. Minimale Referenz-Implementation

Vor dem ersten Deep-Dive-Run erzeugt der Sweep-Driver beim initialen Lauf automatisch:

- `layer3-integration/tests/Security/SecurityAuditTestCase.php` (Skelett mit Helfern aus §2.2)
- `layer4-e2e/helpers/security-audit.ts` (Skelett mit Funktionen aus §3.3)
- `fixtures/security/payloads/.gitkeep`
- Neues Make-Target `test-integration-security-%` in `Makefile`

Diese Erzeugung ist **idempotent**: existieren die Dateien bereits, werden sie nicht überschrieben. Änderungen daran sind manuell durch den User zu pflegen — der Driver modifiziert sie nach Initial-Erzeugung nicht mehr.

## 9. Nicht-Ziele

- Keine Abhängigkeit auf externe PHPUnit-Extensions.
- Keine Parallel-Suite „security-only" als eigene Config — `phpunit-integration.xml` bleibt single-source-of-truth für Layer 3.
- Keine Pflicht, Layer 5 (Performance) für Audit-Tasks zu nutzen. Wenn eine Hypothese auf Timing-Angriffe zielt (`V8`), wird das Timing-Oracle in `SecAudit<NNN>Test` per `microtime()`-Assertions abgebildet — nicht per `make test-performance`.

Weiter: `09_fixture_register.md` (Payload-Struktur, Kategorien, Redaction-Regeln).
