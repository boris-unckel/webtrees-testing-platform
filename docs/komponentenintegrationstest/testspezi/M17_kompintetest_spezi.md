<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M17: Debug-Logger (SQL/Perf)

**Referenz:** M17 | **SUT:** `app/Http/Middleware/DebugLogger.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

[keine L3-Tests vorhanden, nur L2-Stub]

---

## SUT-Kernbefunde

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 — Debug deaktiviert | debug=false → Skip (kein Query-Logging) | Nein |
| B2 — Debug aktiv, Query-Log | debug=true → Query-Log aktivieren, Queries analysieren, Header setzen | Nein |
| B3 — Query-Count > 1000 | Mehr als 1000 Queries → nur erste 1000 loggen | Nein |
| B4 — Bindings sanitizen | Non-ASCII und >30 Zeichen in Bindings → Sanitizing | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | debug=false | Kein Query-Logging, Response unverändert |
| EP2 | debug=true, 0 Queries | Leere Query-Liste, Header gesetzt |
| EP3 | debug=true, 1–999 Queries | Alle Queries geloggt |
| EP4 | debug=true, 1000+ Queries | Nur erste 1000 Queries geloggt |
| EP5 | Bindings mit Sonderzeichen (Non-ASCII) | Sanitized dargestellt |
| EP6 | Non-String Bindings (int, null, bool) | Korrekt konvertiert |

---

## Grenzwerte (BVA)

| Grenze | Werte | Erwartung |
|---|---|---|
| Query-Count | 0 / 1 / 100 / 1000 / 5000 | Grenze bei 1000: alles ab 1001 abgeschnitten |
| Binding-Länge | < 30 Zeichen / exakt 30 / > 30 Zeichen | Ab 31 Zeichen: Truncation |

---

## Empfohlene Strategie

- **Strategie:** EP (Äquivalenzklassenbasiert)
- **Komplexität:** Mittel
- **Testklasse:** `DebugLoggerMiddlewareIntegrationTest`
- **Fixtures:** Request mit debug-Attribut, DB::connection() mit Query-Log
- **Mocking:** RequestHandlerInterface mocken; DB::connection()->enableQueryLog() / getQueryLog() über reale DB-Verbindung testen (MySQL im Container)

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
