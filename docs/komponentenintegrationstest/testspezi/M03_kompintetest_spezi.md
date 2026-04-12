<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — M03: Client-IP-Ermittlung (Proxy-Trust)

**Referenz:** M03 | **SUT:** `app/Http/Middleware/ClientIp.php`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md), [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die Middleware erbt von `\Middlewares\ClientIp` und konfiguriert
trusted_headers/proxies aus Site-Preferences. Es existiert lediglich ein L2-Stub
(`assertTrue(class_exists(...))`), der keine Logik abdeckt.

---

## SUT-Kernbefunde

Die Middleware liest `trusted_headers` und `trusted_proxies` aus den Site-Preferences,
parst CSV-Strings und übergibt sie an die Elternklasse zur IP-Ermittlung.

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `trusted_headers` ist `null` → leeres Array | Nein |
| B2 | `trusted_proxies` ist `null` → leeres Array | Nein |
| B3 | `trusted_headers` ist CSV-String → `explode(',', ...)` | Nein |
| B4 | `trusted_proxies` ist CSV-String → `explode(',', ...)` | Nein |
| B5 | `trusted_headers` ist Leerstring → leeres Array | Nein |
| B6 | `trusted_proxies` ist Leerstring → leeres Array | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Beide Attribute `null` (keine Site-Preferences gesetzt) | Leere Arrays, IP aus REMOTE_ADDR |
| EP2 | Gültige CSV-Strings (`X-Forwarded-For,X-Real-Ip` / `10.0.0.1,10.0.0.2`) | Arrays korrekt befüllt, IP-Ermittlung über Proxy-Header |
| EP3 | Leere Strings (`''`) für beide Attribute | Leere Arrays, Verhalten wie EP1 |

---

## Grenzwerte (BVA)

| Grenzwert | Wert | Erwartung |
|---|---|---|
| CSV mit genau 1 Element | `X-Forwarded-For` | Array mit einem Eintrag |
| CSV mit mehreren Elementen | `X-Forwarded-For,X-Real-Ip,Forwarded` | Array mit drei Einträgen |
| Leerstring vs. `null` | `''` vs. `null` | Beide ergeben leeres Array |

---

## Empfohlene Strategie

- **Testklasse:** `ClientIpMiddlewareIntegrationTest`
- **Strategie:** Spec-C (spezifikationsbasiert, Conditions-Coverage)
- **Priorität:** Niedrig
- **Fixtures:** Site-Preferences in der DB setzen (`trusted_headers`, `trusted_proxies`)
- **Mocking:** Kein Mocking nötig — die Elternklasse `\Middlewares\ClientIp` wird real
  durchlaufen; Request-Objekte mit verschiedenen Header-Konstellationen erzeugen
- **Request-Aufbau:** PSR-7-Requests mit `REMOTE_ADDR`, `X-Forwarded-For` etc.

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
