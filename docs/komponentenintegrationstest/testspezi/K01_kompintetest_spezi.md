<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — K01: Kontaktformular

**Referenz:** K01 | **SUT:** `app/Http/RequestHandlers/ContactPage.php`, `app/Http/RequestHandlers/ContactAction.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Das Kontaktformular besteht aus zwei Handlern: `ContactPage` (GET,
Formular-Anzeige) und `ContactAction` (POST, Formular-Verarbeitung). Die Handler nutzen
`CaptchaService`, `EmailService`, `MessageService`, `RateLimitService` und `UserService`
als Abhängigkeiten. Die Validierung umfasst CAPTCHA-Prüfung, E-Mail-Format, externe Links
im Body und Pflichtfelder.

---

## SUT-Kernbefunde

**ContactPage (GET):**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `to_user` ist `null` → 403 Forbidden | Nein |
| B2 | `to_user` nicht in `validContacts` → 403 Forbidden | Nein |
| B3 | Erfolg → 200 OK, Formular angezeigt | Nein |

**ContactAction (POST):**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B4 | `to_user` ist `null` → 404 Not Found | Nein |
| B5 | `to_user` nicht in `validContacts` → 403 Forbidden | Nein |
| B6 | `body` leer → Redirect + Fehler | Nein |
| B7 | `subject` leer → Redirect + Fehler | Nein |
| B8 | `from_email` leer → Redirect + Fehler | Nein |
| B9 | `from_name` leer → Redirect + Fehler | Nein |
| B10 | CAPTCHA erkennt Robot → Redirect + Fehler | Nein |
| B11 | Ungültige E-Mail-Adresse → Redirect + Fehler | Nein |
| B12 | Externe Links im Body → Redirect + Fehler | Nein |
| B13 | `deliverMessage` gibt `false` zurück → Redirect + Fehler | Nein |
| B14 | Erfolg → Redirect + Success-Flash | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | ContactPage: gültiger Contact-User | 200 OK |
| EP2 | ContactPage: `to` nicht in `validContacts` | 403 Forbidden |
| EP3 | ContactPage: `to` ist `null` | 403 Forbidden |
| EP4 | ContactAction: alle Felder gültig, CAPTCHA OK, Delivery OK | 302 Redirect + Success-Flash |
| EP5 | ContactAction: `body` leer | 302 Redirect + Fehler-Flash |
| EP6 | ContactAction: `subject` leer | 302 Redirect + Fehler-Flash |
| EP7 | ContactAction: `from_email` leer | 302 Redirect + Fehler-Flash |
| EP8 | ContactAction: `from_name` leer | 302 Redirect + Fehler-Flash |
| EP9 | ContactAction: CAPTCHA erkennt Robot | 302 Redirect + Fehler-Flash |
| EP10 | ContactAction: ungültige E-Mail (z. B. `not-an-email`) | 302 Redirect + Fehler-Flash |
| EP11 | ContactAction: externe Links im Body (`http://spam.example.com`) | 302 Redirect + Fehler-Flash |
| EP12 | ContactAction: `deliverMessage` gibt `false` zurück | 302 Redirect + Fehler-Flash |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| `body` Länge 0 | `''` | Redirect + Fehler |
| `body` Länge 1 | `'x'` | Gültig |
| `subject` Länge 0 | `''` | Redirect + Fehler |
| `subject` Länge 1 | `'x'` | Gültig |
| `from_email` Länge 0 | `''` | Redirect + Fehler |
| `from_email` Länge 1 | `'x'` | Ungültige E-Mail → Fehler |
| `from_name` Länge 0 | `''` | Redirect + Fehler |
| `from_name` Länge 1 | `'x'` | Gültig |
| E-Mail Grenzfall | `a@b.c` | Gültig (minimale E-Mail) |

---

## Empfohlene Strategie

- **Testklasse:** `ContactFormIntegrationTest`
- **Strategie:** EP (Äquivalenzklassen-basiert)
- **Priorität:** Mittel
- **Fixtures:** Benutzer in DB anlegen (als Contact-User konfiguriert), Tree anlegen
- **Dependencies:** `CaptchaService`, `MessageService` per Mock (kein SMTP nötig);
  `UserService`, `RateLimitService` real durchlaufen
- **Mocking:** `MessageService::deliverMessage()` mocken (Erfolg/Misserfolg steuerbar);
  `CaptchaService` mocken (Robot-Erkennung steuerbar)
- **Besonderheit:** Kein SMTP-Server nötig durch Mocking des `MessageService`

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `ContactFormIntegrationTest [EP] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte auf `2, 3` erweitern (aktuell nur `3`) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen (K01 ggf. ergänzen) |
| `docs/tds_methodik_spec.md` | Ggf. Kontaktformular-ohne-SMTP als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
