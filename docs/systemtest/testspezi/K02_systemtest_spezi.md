<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — K02: Benutzer-Nachrichten

**Referenz:** K02 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/message-compose` (GET Page), `/tree/{tree}/message-send` (POST Action) → `MessagePage`, `MessageAction`
**L3-Referenztest:** keiner (Upstream-Ableitung)
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md), Abschnitt 7

---

## Status quo

Für die Benutzer-Nachrichten-Funktion existieren bisher keine L4-Systemtests. Es existieren auch keine L3-Komponentenintegrationstests für dieses Feature — die Testszenarien werden direkt aus dem Upstream-Code abgeleitet (Konzept 7). Im Unterschied zum Kontaktformular (K01) erfordert dieses Feature einen eingeloggten Benutzer (AuthLoggedIn), verwendet kein CAPTCHA und hat kein Rate-Limit.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/message-compose` | GET | `MessagePage` |
| `/tree/{tree}/message-send` | POST | `MessageAction` |

Beide Handler erfordern `AuthLoggedIn` — Login ist zwingend erforderlich. Der GET-Handler rendert das Nachrichten-Formular mit den Feldern `subject`, `body` und `to` (hidden mit Anzeigename). Der POST-Handler validiert den Empfänger und die Pflichtfelder.

### View-Analyse

Das Nachrichten-Formular (`message-page` View) nutzt Bootstrap-basierte Eingabefelder:

- `input[name="subject"]` — Betreff (required text)
- `textarea[name="body"]` — Nachrichtentext (required textarea, rows=5)
- `input[name="to"]` — Empfänger (hidden, Anzeigename sichtbar)

Kein CAPTCHA (eingeloggter Benutzer). Keine E-Mail-Validierung, keine externe-Links-Prüfung.

### Theme-Abhängigkeit

Die Formular-Funktionalität ist nicht theme-abhängig. Das Layout variiert minimal zwischen Themes (Bootstrap-Standard), aber die funktionalen Elemente (`name`-Attribute, Submit-Button) sind identisch. Kein Theme-Loop erforderlich — die Interaktion ist rein funktional.

---

## L3-Referenz-Analyse

keiner — Upstream-Ableitung gemäß Konzept 7.

Direkte Code-Analyse der Handler-Klassen:

**MessagePage (GET):**
- Empfänger-Validierung via `$to` Query-Parameter → `UserService.findByUserName`
- Kontaktmethode des Empfängers muss erlaubt sein (`PREF_CONTACT_METHOD !== NONE`)
- Bei ungültigem Empfänger: `HttpAccessDeniedException`
- Rendert `message-page` View mit Feldern `subject`, `body`, `to`

**MessageAction (POST):**
1. Empfänger existiert + Kontaktmethode erlaubt → sonst `HttpAccessDeniedException`
2. Pflichtfelder (`body`, `subject`) nicht leer → Redirect zu MessagePage ohne Flash
3. Kein CAPTCHA, keine E-Mail-Validierung, keine externe-Links-Prüfung, kein Rate-Limit
4. Erfolg: Flash "successfully sent to {name}", Redirect zu Ausgangs-URL
5. Fehler: Flash "not sent", Redirect zu MessagePage

**Unterschied zu K01:** K02 erfordert Login (AuthLoggedIn), K01 erlaubt Gast-Zugriff. K02 hat kein CAPTCHA, kein Rate-Limit, keine externe-Links-Prüfung. Der Absender wird implizit aus dem eingeloggten User ermittelt (keine `from_name`/`from_email`-Felder).

---

## Bestehende L4-Muster-Analyse

Als Referenz dienen `login.spec.ts` (Formular-Pattern: Felder ausfüllen, Submit, Ergebnis-Verifikation) und `user-pages.spec.ts` (Login-beforeEach-Pattern mit `ADMIN_PASSWORD`). Die Nachrichten-Funktion folgt dem Konzept 1 (Formular-Submit-Verification) ohne Theme-Loop, da die Funktionalität nicht theme-abhängig ist.

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Nachrichten-Formular rendert korrekt (subject, body Felder sichtbar, Empfängername angezeigt) | Member | Seite lädt (200), Formularfelder sichtbar, Empfängername im Formular angezeigt | Nein |
| T2 | Nachricht senden via Submit → Bestätigungsmeldung oder Redirect | Member | Alle Felder ausgefüllt, Submit → Flash "successfully sent" oder Redirect zur Ausgangs-URL | Nein |
| T3 | Leere Pflichtfelder → Redirect zurück zum Formular | Member | Absenden ohne Eingabe → Redirect zurück zur MessagePage, Formular bleibt funktional | Nein |

---

## Playwright-Pattern

**Gewähltes Pattern:** Formular-Submit-Verification (Konzept 1)
**Begründung:** Die Nachrichten-Funktion erfordert einen eingeloggten Benutzer und ist rein funktional (kein Theme-Loop nötig). T1 ist Smoke-Level (Formular lädt), T2 ist Spec-C (Nachricht senden mit fachlich sichtbarem Effekt — Flash-Message), T3 prüft die Pflichtfeld-Validierung. E-Mail-Versand ist in L4 nicht prüfbar — die Verifikation beschränkt sich auf Redirect-Ziel und Flash-Messages. Kein Theme-Loop, da die Formular-Funktionalität nicht theme-abhängig ist.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `user-messages.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsRole` (privacy-roles) oder direkter Login via `ADMIN_PASSWORD`/`TEST_USER_PASSWORD` |
| **Theme-Loop** | Nein — Formular-Funktionalität, nicht theme-abhängig |
| **Login-Strategie** | Login als Member (`test-member` mit `TEST_USER_PASSWORD`) oder Admin |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `user-messages.spec.ts` [Spec-C] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Einschränkungen

- **E-Mail-Versand:** In L4 nicht prüfbar — Verifikation beschränkt sich auf Redirect/Flash-Message.
- **Empfänger (`to`):** Der `to`-Parameter muss als Query-Parameter in der URL mitgegeben werden. Konkreter Empfänger-Username: `admin` (validContact des demo-Baums). URL-Aufruf daher: `/tree/demo/message-compose?to=admin`.
- **Login-Voraussetzung:** Test-User `test-member` muss im demo-Baum als Mitglied angelegt sein (erfolgt in `setup-webtrees.sh`). Falls `test-member` im demo-Baum nicht vorhanden, auf `admin`-Login ausweichen.
- **Kontaktmethode:** Der Empfänger muss eine erlaubte Kontaktmethode konfiguriert haben (`PREF_CONTACT_METHOD !== NONE`). Der Admin-User hat standardmäßig alle Kontaktmethoden aktiviert.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
