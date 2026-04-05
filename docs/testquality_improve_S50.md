<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S50: Hilfetexte

**Referenz:** S50 | **SUT:** `app/Http/RequestHandlers/HelpText.php`  
**Aktueller Test:** `RequestHandlerBatchAIntegrationTest` (4 Tests: DATE, NAME, pending_changes, relationship-privacy → 200)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

4 Tests mit bekannten gültigen Hilfetext-IDs, alle prüfen nur HTTP 200. Kein Test für unbekannte IDs, kein Test für den Inhalt der Hilfetext-Antwort.

---

## SUT-Kernbefunde

`HelpText::handle()` lädt lokalisierte Hilfetext-Templates:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| Bekannte Topic-ID | Template existiert → 200 | ✅ (4 IDs) |
| Unbekannte Topic-ID | Template nicht gefunden → ??? | ❌ |
| Lokalisierung | I18N-Sprache beeinflusst Template | ❌ |
| Topic mit Sonderzeichen | `../`, `script>`, Null-Byte | ❌ (Sicherheitsrelevant) |
| Leere Topic-ID | `topic=''` | ❌ |
| Response-Inhalt | HTML enthält Hilfetext-Inhalt | ❌ |

**Besonderheit:** Der SUT hat eine begrenzte, bekannte Menge gültiger Topic-IDs. Das macht ihn ideal für vollständige EP-Abdeckung.

---

## Äquivalenzklassen (EP)

| Klasse | Topic-ID | Erwartung |
|---|---|---|
| EP1–EP4 | `DATE`, `NAME`, `pending_changes`, `relationship-privacy` | ✅ bereits abgedeckt |
| EP5 | Weitere bekannte IDs (z.B. `SOUR`, `NOTE`, `PLAC`) | 200, Inhalt nicht leer |
| EP6 | Unbekannte ID (`nonexistent_xyz`) | 200 + generischer Hilfetext „not been written" (default-Case) |
| EP7 | Leere ID (`''`) | 200 + generischer Hilfetext (topic='' matched default-Case) |
| EP8 | ID mit Pfad-Traversal (`../config`) | Sicherheits-Test |

---

## Grenzwerte (BVA)

- Topic-ID-Länge: 1 Zeichen, typische Länge, sehr lang
- Sonderzeichen: `<script>`, `%00`, `../`

---

## Empfohlene Strategie

**ISTQB B** für Topic-ID-EP — vollständige Liste der gültigen IDs aus dem SUT ableitbar (Template-Dateien enumerable). Alle gültigen IDs per DataProvider testen ist umsetzbar und wertvoll.  
**Pragmatisch C** für unbekannte IDs und Sicherheits-Tests.

Dies ist der **niedrig-aufwändigste** Kandidat für vollständige EP-Abdeckung.

---

## Konkrete Testideen

```
// DataProvider aller gültiger Topic-IDs (aus SUT-Templates ableiten)
test_all_valid_help_topics_return_200(string $topic)  ← DataProvider
test_unknown_help_topic_returns_404_or_not_found()
test_empty_topic_id_handled_gracefully()
test_help_text_response_contains_non_empty_content()
```

---

## Aufwand

**Niedrig** — DataProvider mit allen 12 bekannten Topic-IDs (aus switch-Statement im SUT enumieriert). Response ist HTML, nicht JSON (Spec-Korrektur).

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | EP6/EP7 korrigiert: kein 404, default-Case → 200+generisch; Response ist HTML |
| P2: Soll-Design | ✅ DONE | Neue Klasse HelpTextIntegrationTest: DataProvider 12 Topics + unbekannte ID |
| P3: Test-Coding | ✅ DONE | Neue HelpTextIntegrationTest.php: DataProvider 12 Topics + unknown-Topic-Test |
| P4: Ausführung + Fixing | ✅ DONE | 13/13 grün, 52 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren, Abdeckungsmatrix, Endekriterien, Zusammenfassung konsolidiert, Changelog aktualisiert |
