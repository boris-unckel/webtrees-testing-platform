<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — K02: Benutzer-Nachrichten

**Referenz:** K02 | **SUT:** `app/Http/RequestHandlers/MessagePage.php`, `app/Http/RequestHandlers/MessageAction.php`, `app/Http/RequestHandlers/MessageSelect.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Das Benutzer-Nachrichten-System besteht aus drei Handlern:
`MessagePage` (GET, Formular-Anzeige), `MessageAction` (POST, Nachricht senden) und
`MessageSelect` (POST, Redirect-Logik). Im Gegensatz zum Kontaktformular (K01) ist hier
ein authentifizierter Benutzer erforderlich (kein Guest-Zugriff). Die Handler nutzen
`MessageService` und `UserService` als Abhängigkeiten.

---

## SUT-Kernbefunde

**MessagePage (GET):**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `to` ist `null` → 403 Forbidden | Nein |
| B2 | `CONTACT_METHOD` ist `NONE` → 403 Forbidden | Nein |
| B3 | Erfolg → 200 OK, Formular angezeigt | Nein |

**MessageAction (POST):**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B4 | `to` ist `null` → 403 Forbidden | Nein |
| B5 | `CONTACT_METHOD` ist `NONE` → 403 Forbidden | Nein |
| B6 | `body` leer → Redirect | Nein |
| B7 | `subject` leer → Redirect | Nein |
| B8 | `deliverMessage` gibt `false` zurück → Redirect + Fehler | Nein |
| B9 | Erfolg → Redirect + Success-Flash | Nein |

**MessageSelect (POST):**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B10 | Reine Redirect-Logik (POST → GET) | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | MessagePage: gültiger Empfänger, `CONTACT_METHOD` erlaubt | 200 OK |
| EP2 | MessagePage: `to` ist `null` | 403 Forbidden |
| EP3 | MessagePage: `CONTACT_METHOD` ist `NONE` | 403 Forbidden |
| EP4 | MessageAction: alle Felder gültig, Delivery OK | 302 Redirect + Success-Flash |
| EP5 | MessageAction: `body` leer | 302 Redirect |
| EP6 | MessageAction: `subject` leer | 302 Redirect |
| EP7 | MessageAction: `body` und `subject` beide leer | 302 Redirect |
| EP8 | MessageAction: `deliverMessage` gibt `false` zurück | 302 Redirect + Fehler-Flash |
| EP9 | MessageSelect: POST → GET Redirect | 302 Redirect |

---

## Grenzwerte (BVA)

Keine signifikanten Grenzwerte jenseits der EP-Abdeckung — die Validierung ist binär
(leer/nicht leer, erlaubt/nicht erlaubt).

---

## Empfohlene Strategie

- **Testklasse:** `UserMessageIntegrationTest`
- **Strategie:** EP (Äquivalenzklassen-basiert)
- **Priorität:** Mittel
- **Fixtures:** Zwei Benutzer in DB anlegen (Sender + Empfänger), Tree anlegen,
  `CONTACT_METHOD`-Preference setzen
- **Dependencies:** `MessageService` per Mock (kein SMTP nötig); `UserService` real durchlaufen
- **Mocking:** `MessageService::deliverMessage()` mocken (Erfolg/Misserfolg steuerbar)
- **Besonderheit:** Authentifizierter User nötig (kein Guest-Zugriff) — Session/Auth im
  Test-Setup konfigurieren. `CONTACT_METHOD`-Preference muss für den Empfänger-User
  korrekt gesetzt sein.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `UserMessageIntegrationTest [EP] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte auf `2, 3` erweitern (aktuell nur `3`) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen (K02 ggf. ergänzen) |
| `docs/tds_methodik_spec.md` | Ggf. Nachrichten-Handler-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
