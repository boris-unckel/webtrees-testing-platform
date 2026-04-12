<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — K01: Kontaktformular

**Referenz:** K01 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/contact` (GET Page + POST Action) → `ContactPage`, `ContactAction`
**L3-Referenztest:** keiner (Upstream-Ableitung)
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md), Abschnitt 7

---

## Status quo

Für das Kontaktformular existieren bisher keine L4-Systemtests. Die bestehende `user-pages.spec.ts` prüft lediglich das Rendering der Kontaktseite (S36 — GET→200, body sichtbar), nicht die Formular-Interaktion (Pflichtfeld-Validierung, Submit, Flash-Messages). Es existieren auch keine L3-Komponentenintegrationstests für dieses Feature — die Testszenarien werden direkt aus dem Upstream-Code abgeleitet (Konzept 7).

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/contact` | GET | `ContactPage` |
| `/tree/{tree}/contact` | POST | `ContactAction` |

Beide Handler nutzen `AuthNotRobot` — Gäste sind erlaubt, kein Login nötig. Der GET-Handler rendert das Kontaktformular mit den Feldern `from_name`, `from_email`, `subject`, `body`, `to` (hidden) und CAPTCHA. Der POST-Handler validiert den Empfänger, die Pflichtfelder, CAPTCHA, E-Mail-Format und prüft auf externe Links in Subject/Body.

### View-Analyse

Das Kontaktformular (`contact-page` View) nutzt Bootstrap-basierte Eingabefelder:

- `input[name="from_name"]` — Absender-Name (required text)
- `input[name="from_email"]` — Absender-E-Mail (required email)
- `input[name="subject"]` — Betreff (required text)
- `textarea[name="body"]` — Nachrichtentext (required textarea, rows=5)
- `input[name="to"]` — Empfänger (hidden)
- CAPTCHA-Felder (`x`, `y`, `z`, `t`)

### Theme-Abhängigkeit

Das Formular-Layout (Labels, Feldanordnung, Abstände) variiert zwischen Themes. Die funktionalen Elemente (`name`-Attribute, Submit-Button) sind theme-unabhängig. Theme-Loop ist sinnvoll, da das Rendering der Formularfelder und der Flash-Messages theme-abhängig sein kann.

---

## L3-Referenz-Analyse

keiner — Upstream-Ableitung gemäß Konzept 7.

Direkte Code-Analyse der Handler-Klassen:

**ContactPage (GET):**
- Empfänger-Validierung via `$to` Query-Parameter → `UserService.findByUserName` → `MessageService.validContacts(tree)`
- Bei ungültigem Empfänger: `HttpAccessDeniedException`
- Rendert `contact-page` View mit allen Formularfeldern

**ContactAction (POST):**
1. Empfänger existiert + ist `validContact` → sonst `HttpNotFoundException`/`HttpAccessDeniedException`
2. Pflichtfelder (`body`, `subject`, `from_email`, `from_name`) nicht leer → Flash-Fehler
3. CAPTCHA prüfen → Flash "Please try again."
4. E-Mail-Validierung → Flash "valid email"
5. Externe Links in Subject/Body → Flash "not allowed to send messages that contain external links"
6. Rate Limit: 20 Nachrichten pro 1200s pro Empfänger
7. Erfolg: Flash "successfully sent to {name}", Redirect zu Ausgangs-URL
8. Fehler: Flash "not sent", Redirect zurück zu ContactPage mit Formulardaten

---

## Bestehende L4-Muster-Analyse

`user-pages.spec.ts` (S36) testet nur das Seiten-Rendering der Kontaktseite (Smoke-Level: GET→200, body sichtbar). Die Formular-Interaktion (Felder ausfüllen, Submit, Flash-Message-Verifikation) ist ein neues Pattern, das dem Konzept 1 (Formular-Submit-Verification) aus den übergreifenden Konzepten folgt. Als Referenz für das Login-freie Formular-Pattern dient `login.spec.ts` (Formular ausfüllen, Submit, Ergebnis-Verifikation ohne vorheriges Login).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Kontaktformular rendert korrekt (Felder from_name, from_email, subject, body sichtbar) | Visitor | Seite lädt (200), Formularfelder sichtbar, Submit-Button vorhanden | Ja |
| T2 | Pflichtfelder prüfen — leeres Formular absenden → Fehlermeldung oder Redirect | Visitor | Absenden ohne Eingabe führt zu Fehlermeldung (Flash) oder Redirect zurück zum Formular | Ja |
| T3 | Kontaktformular ausfüllen und absenden → Bestätigungsmeldung oder Redirect | Visitor | Alle Felder ausgefüllt, Submit → Flash "successfully sent" oder Redirect zur Ausgangs-URL | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Formular-Submit-Verification (Konzept 1)
**Begründung:** Das Kontaktformular ist für Gäste zugänglich (kein Login nötig). Das Formular-Rendering variiert zwischen Themes (Bootstrap-Layout). T1 ist Smoke-Level (Formular lädt), T2/T3 sind Spec-C (Formular-Interaktion mit fachlich sichtbarem Effekt — Flash-Message-Verifikation). E-Mail-Versand ist in L4 nicht prüfbar — die Verifikation beschränkt sich auf Redirect-Ziel und Flash-Messages.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `contact-form.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | Theme-Loop-Helper (`themes`, `switchTheme`) |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Kein Login (Gast-Zugriff, AuthNotRobot) |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `contact-form.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Einschränkungen

- **E-Mail-Versand:** In L4 nicht prüfbar — Verifikation beschränkt sich auf Redirect/Flash-Message.
- **CAPTCHA:** In der Test-Umgebung möglicherweise deaktiviert oder mit bekanntem Seed — in Phase P3 prüfen und ggf. CAPTCHA-Felder mit korrekten Werten befüllen.
- **Empfänger (`to`):** Der `to`-Parameter muss als Query-Parameter in der URL oder als Hidden-Field mitgegeben werden. Konkreter Empfänger-Username: `admin` (validContact des demo-Baums). URL-Aufruf daher: `/tree/demo/contact?to=admin`.
- **Rate Limit:** Bei wiederholter Testausführung kann das Rate-Limit (20 Nachrichten pro 1200s) greifen — in P4 berücksichtigen.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
