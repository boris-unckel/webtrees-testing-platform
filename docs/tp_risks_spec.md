<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Risikomanagement und Fehlermanagement

Dieses Dokument konsolidiert Produktrisiken, Projektrisiken, Fehlermanagement und bekannte Fehler im Teststack. Die Produktrisiken leiten die Priorisierung der [Feature-Matrizen](tds_conditions_ref.md) her. Die [Überdeckungsstrategie](tp_ratchet_spec.md) beschreibt, wie die Testabdeckung systematisch erhöht wird.

---

## Produktrisiken

> Leiten die Priorisierung der Feature-Matrix her (ISTQB: **risikobasiertes Testen**).

| Risiko-ID | Risiko | Wahrscheinlichkeit | Auswirkung | Maßnahme (Feature-Matrix-IDs) |
|---|---|---|---|---|
| R1 | GEDCOM-Import verliert Daten (Records, Beziehungen, Orte) | Mittel | Kritisch | G01–G04, G07–G09 (alle Hoch) |
| R2 | Privacy-Leak beim Export (geschützte Records sichtbar) | Niedrig | Kritisch | G16 (Hoch) |
| R3 | Suche liefert falsche/unvollständige Ergebnisse | Mittel | Hoch | S01–S02, S04, S12 (alle Hoch) |
| R4 | Import-Export-Roundtrip nicht verlustfrei | Mittel | Hoch | G20 (Hoch) |
| R5 | Charts rendern fehlerhaft nach Update | Mittel | Mittel | S14, S16, S18 (Hoch/Mittel) |
| R6 | Encoding-Konvertierung fehlerhaft (Zeichenverlust) | Niedrig | Hoch | G07, G08, G17 (Hoch/Mittel) |
| R7 | Performance-Regression nach webtrees-Update | Mittel | Mittel | Performanztest mit Baseline-Vergleich |
| R8 | Privacy-Leak: Lebende Person für Besucher sichtbar | Mittel | Kritisch | P01–P07, P10–P11, P22–P24 |
| R9 | Privacy-Leak: Vertrauliche Fakten (Geburtsdatum, SSN) sichtbar | Niedrig | Kritisch | P17–P19 |
| R10 | isDead()-Fehlklassifikation an Datumsgrenzen | Mittel | Hoch | P08–P13 |
| R11 | RESN-Tags werden ignoriert oder falsch interpretiert | Niedrig | Hoch | P16–P21 |
| R12 | Bearbeiter kann ohne Berechtigung Daten ändern | Niedrig | Hoch | P27–P29 |
| R13 | Relationship Privacy zeigt entfernte/unverwandte Personen | Niedrig | Mittel | P22–P23 |
| R14 | DB-Credentials über HTTP zugänglich (`data/config.ini.php`) | Niedrig | Kritisch | SEC-H03, SEC-H04, SEC-H06 |
| R15 | Setup-Wizard nach Ersteinrichtung erneut aufrufbar (Admin-Takeover) | Niedrig | Kritisch | SEC-W01, SEC-WZ04 |
| R16 | Mediendateien ohne Zugriffskontrolle per Direkt-URL abrufbar | Niedrig | Hoch | SEC-M01–SEC-M03, SEC-H05 |
| R17 | Path-Traversal ermöglicht Dateizugriff außerhalb `/public/` | Niedrig | Kritisch | SEC-PUB04 |
| R18 | Fehlende Security-Headers ermöglichen Clickjacking/MIME-Sniffing | Mittel | Mittel | SEC-HDR01–SEC-HDR03 |
| R19 | `config.ini.php` world-readable (fehlender `chmod` im Wizard) | Mittel | Hoch | SEC-C03 |
| R20 | Schutzdateien (`data/.htaccess`, `data/index.php`) fehlen in Distribution | Niedrig | Kritisch | SEC-H01, SEC-H02, SEC-D01, SEC-D02 |
| R21 | Server-Banner verrät Apache-Version (Information Disclosure) | Hoch | Niedrig | SEC-HDR04 |

---

## Projektrisiken

- **Upstream lehnt PR ab:** Saubere Commit-Historie, webtrees-Coding-Standards (PSR-12, PHPStan Level 2), kleine fokussierte PRs pro Domäne minimieren das Risiko. Fallback: Tests bleiben im eigenen Repo nutzbar.
- **Container-Stack funktioniert nicht auf GitHub Actions:** Phase 1 (Testumgebung) wird als erstes implementiert und auf GitHub Actions validiert, bevor weitere Teststufen aufgebaut werden.
- **Monatelange Pause zwischen Implementierungsphasen:** Wartbarkeit ist höchste Priorität (Designentscheidung). Testkonventionen, Verfolgbarkeit und selbstdokumentierende Testnamen adressieren dieses Risiko.
- **webtrees-Update ändert interne APIs:** Tests basieren auf öffentlichen Service-APIs, nicht auf internen Implementierungsdetails. Komponentenintegrationstests nutzen die webtrees-API, nicht direkte DB-Manipulation.

---

## Fehlermanagement

> Pragmatischer Prozess für ein Ein-Personen-Projekt. Kein formaler Issue-Lifecycle.

**Prinzip:** CI-Gate = Fehlermanagement. Rot = blockiert, Grün = freigegeben.

| Fehlerzustand in... | Vorgehen |
|---|---|
| **Eigener Testinfrastruktur** | Direkt im Code beheben (Fix-Commit), kein separater Issue-Tracker |
| **webtrees Core** | Issue bei `fisharebest/webtrees` erstellen; Referenz auf Feature-Matrix-ID; ggf. Fix-PR |
| **Testdaten (Fixture)** | Fixture korrigieren, Testerwartungen anpassen |
| **Apache-Konfiguration** (z.B. Server-Banner) | Dokumentieren als Deployment-Empfehlung. Kein Upstream-Issue, da nicht webtrees-Code. |

`analyze-failure.sh` unterstützt die Grundursachenanalyse (ISTQB: Grundursachenanalyse)
durch Artefakt-Sammlung und Claude Code CLI als Analyse-Tool.

---

## Bekannte Fehler im Teststack

### HOST-Bug: SELinux MCS-Label-Konflikt (Fedora/rootless Podman)

**Symptom:** `podman-compose exec webtrees bash -c "ls /var/www/html"` → Permission denied
**Ursache:** `github/webtrees` (Bind-Mount-Quelle) trägt noch MCS-Kategorien (z. B. `:c607,c731`) eines früheren Containers. Der neue Container hat andere Kategorien (z. B. `:c431,c971`) → SELinux verweigert den Zugriff.
**Betrifft:** Nur diesen Dev Desktop (Fedora + SELinux + rootless Podman). Auf anderen Systemen ohne SELinux tritt dieser Fehler nicht auf.
**Recovery (manuell, einmalig nach Auftreten):**
```bash
chcon -R -l s0 /pfad/zum/webtrees-checkout/
make down && make up
```
**Status:** Nicht automatisch behebbar (Host-spezifisch). Dokumentiert in CLAUDE.md unter „SELinux-Falle".

---

### Upstream-Bug: `FamilyFactory::mapper()` TypeError bei Privat-Familien

**Symptom:** `TypeError` in `FamilyFactory::mapper()` beim Zugriff auf Familien mit Privacy-Level PRIV_NONE oder PRIV_USER.
**Ursache:** Die Mapper-Funktion gibt `null` für eingeschränkte Familien zurück, ohne dass der aufrufende Code dies erwartet.
**Betrifft:** Export mit Privacy-Filterung (G16, Access-Levels PRIV_NONE/PRIV_USER) und Citation-AutoComplete (Prio 3a).
**Status:** Offen. Upstream-Bug bei `fisharebest/webtrees` zu melden. Tests für betroffene Access-Levels sind übersprungen (`1 Skipped`).

---

### Upstream-Befund: `config.ini.php` world-readable (SEC-C03)

**Symptom:** Setup-Wizard erzeugt `config.ini.php` mit Permissions 644 (world-readable).
**Ursache:** `SetupWizard::createConfigFile()` nutzt `file_put_contents()` ohne anschließendes `chmod`. Die Datei erbt den umask-Default des PHP-Prozesses.
**Betrifft:** Shared-Hosting-Umgebungen, in denen andere Nutzer die Datei lesen können (DB-Credentials).
**Status:** Dokumentiert als Upstream-Befund. Test `SEC-C03` bleibt rot mit Annotation.

---

### Deployment-Empfehlung: Apache Server-Banner (SEC-HDR04)

**Symptom:** HTTP-Response enthält `Server: Apache/2.x.x ...` mit vollständiger Versionsangabe.
**Ursache:** Apache ServerTokens Default (`Full`) gibt Versionsinfo preis. Kein webtrees-Code, sondern Apache-Konfiguration.
**Empfehlung:** `ServerTokens Prod` in der Apache-Konfiguration der Produktionsumgebung setzen.
**Status:** Test `SEC-HDR04` als `test.fixme()` markiert (erwartetes Scheitern, nicht blockierend).

---

## Aktuelle Testergebnisse

**Aktuelle Testergebnisse** finden sich in den CI-Artefakten des letzten GitHub-Actions-Laufs
(7 Tage Retention) oder lokal via `make test-all`. Die Artefakte pro Teststufe liegen in
`artifacts/layer1/` bis `artifacts/layer5/`.
