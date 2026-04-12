<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M09: Base-URL-Ermittlung

**Referenz:** M09 | **SUT:** `app/Http/Middleware/BaseUrl.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware ermittelt die Base-URL entweder aus der
Site-Preference oder per Auto-Detection aus dem aktuellen Request. Es existiert
lediglich ein L2-Stub (`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware nutzt keine Dependency-Injection, sondern `Validator` und `parse_url()`
zur URL-Verarbeitung. Die Logik unterscheidet zwischen konfigurierter und
auto-detektierter Base-URL.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `base_url` leer → Auto-Detection via Request-URI | Nein |
| B2 | `base_url` leer + URI enthält `index.php` → `explode('index.php', ...)` | Nein |
| B3 | `base_url` leer + URI ohne `index.php` → URI direkt nutzen | Nein |
| B4 | `base_url` gesetzt → Scheme/Host/Port aus `parse_url()` übernehmen | Nein |
| B5 | `base_url` gesetzt ohne Port → Default-Port (80/443) | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | `base_url` leer, Request mit `index.php` im Pfad | Auto-Detection: Pfad vor `index.php` als Base |
| EP2 | `base_url` leer, Request ohne `index.php` | Auto-Detection: Voller Request-URI als Base |
| EP3 | `base_url` gesetzt (`https://example.com/family`) | Scheme, Host, Port aus konfigurierter URL |
| EP4 | `base_url` gesetzt ohne expliziten Port | Default-Port wird genutzt |
| EP5 | `base_url` gesetzt mit explizitem Port | Expliziter Port wird übernommen |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| Leere `base_url` | `''` | Auto-Detection-Pfad |
| Minimale `base_url` | `http://localhost` | Scheme=http, Host=localhost, Port=80 |
| `base_url` mit Port | `https://example.com:8080/path` | Port=8080 korrekt extrahiert |
| `base_url` mit Trailing-Slash | `https://example.com/family/` | Trailing-Slash-Handling korrekt |

---

## Empfohlene Strategie

- **Testklasse:** `BaseUrlMiddlewareIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Mittel
- **Fixtures:** Site-Preferences in der DB setzen (`base_url`); PSR-7-Requests mit
  verschiedenen URIs und Host-Headern erzeugen
- **Mocking:** Kein Mocking nötig — reine URL-Logik, die über Request-Attribute
  und Site-Preferences gesteuert wird
- **Hinweis:** Die Auto-Detection (B1–B3) hängt vom Request-URI ab und kann
  vollständig über Request-Objekte gesteuert werden.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `<Testklasse> [<Siegel>] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte prüfen (muss `2` enthalten) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen |
| `docs/tds_methodik_spec.md` | Ggf. Middleware-Pipeline-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
