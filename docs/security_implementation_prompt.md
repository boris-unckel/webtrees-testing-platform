# Prompt — Implementierung Sicherheitstests

Implementiere den Plan in `docs/security_plan.md`, Phasen S1–S6.

## Regeln

1. **Status tracken:** Aktualisiere bei jeder Statusänderung die Phasen-Übersicht, die Phase-Detail-Tabelle und die Status-Spalte in der Feature-Matrix (Abschnitt 4.2) in `docs/security_plan.md`.
2. **Kleinteilig:** Jede Phase einzeln implementieren, testen. Keine Phase überspringen.
3. **Security-Läufe:** `make test-security` (oder Teilaufrufe) jederzeit zum Testen und Bugfixen erlaubt.
4. **Upstream-Befunde:** Test bleibt rot, Annotation im Code, Status in Feature-Matrix auf `Rot (Upstream-Befund)`.
5. **Gesamttest am Ende:** Nach S5 einen vollständigen Testlauf aller Teststufen durchführen (`make test-unit && make test-integration && make test-e2e && make test-security`). Fehler fixen, bis alles grün ist. Erst danach S6.
6. **Phase S6 (Doku) erst am Ende:** Bigpicture-Update erst, wenn S1–S5 verifiziert sind und der Gesamttest steht.
7. **Keine commits:** git commit wird manuell ausgelöst.
8. **Kontext:** `docs/testing-bigpicture.md` für bestehende Strukturen, `CLAUDE.md` für Infrastruktur.

## Fazit

### Gesamttest — Alle vier Stufen grün

| Stufe | Ergebnis |
|---|---|
| Layer 2 — Unit | 3397 passed (exit 0) |
| Layer 3 — Integration | 274 passed (exit 0) |
| Layer 4 — E2E | 176 passed (exit 0) |
| Security | 30 passed, 1 skipped (SEC-HDR04), FS 8/9 + 1 Upstream-Befund |

### Änderungen seit dem letzten Commit

**Neue Dateien (S1–S5):**
- `Containerfile.security` — Multi-Stage Build für Distribution-Container
- `scripts/build-security-image.sh` — Build-Helper mit `podman build --volume`
- `scripts/security-filesystem-checks.sh` — 9 Dateisystem-Assertions (pre/post-wizard)
- `layer4-e2e/playwright-security.config.ts` — Playwright-Config für Sicherheitstests
- `layer4-e2e/tests/security/wizard-setup.spec.ts` — SEC-WZ01–WZ04
- `layer4-e2e/tests/security/data-access.spec.ts` — SEC-H03–H06
- `layer4-e2e/tests/security/public-access.spec.ts` — SEC-PUB02–PUB04
- `layer4-e2e/tests/security/setup-lock.spec.ts` — SEC-W01
- `layer4-e2e/tests/security/media-access.spec.ts` — SEC-M01–M03
- `layer4-e2e/tests/security/security-headers.spec.ts` — SEC-HDR01–HDR04

**Geänderte Dateien:**
- `compose.yaml` — Security-Profile (webtrees-security, mysql-security)
- `Makefile` — Security-Targets (security-build, test-security, security-up, security-down, security-clean)
- `layer4-e2e/playwright.config.ts` — `testIgnore: '**/security/**'` (Trennung E2E/Security)
- `docs/security_plan.md` — Status-Updates aller Phasen und Checkpoints

**Bekannte Befunde:**
- SEC-C03: config.ini.php world-readable (644) — Upstream-Befund, kein chmod im Wizard
- SEC-HDR04: Apache Server-Banner enthält Version — Deployment-Empfehlung (ServerTokens Prod)
