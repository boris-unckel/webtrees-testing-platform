<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# prep-01 — Umgebung starten und Coverage erzeugen

---

## Stack starten

```bash
# 1. Laufende Testprozesse prüfen
pgrep -a phpunit && echo "Aktiver Lauf — erst warten oder per kill beenden"

# 2. Alte Coverage entfernen
rm -f artifacts/layer3/coverage.xml

# 3. Stack starten — IMMER make up, NIEMALS make _compose-up direkt
#    (make up ruft intern generate-passwords auf — ohne das bleibt .env leer)
make up

# 4. webtrees einrichten (falls nicht bereits geschehen)
make setup
```

## Coverage erzeugen

```bash
# run_in_background: true — läuft deutlich länger als 10 Minuten
# Kein timeout-Parameter setzen
make test-integration
```

Ergebnis: `artifacts/layer3/coverage.xml`

Auf die Fertigmeldung warten, bevor der nächste Schritt beginnt.

## CRAP-Report erzeugen

```bash
make crap-report
```

Ausgabe: Tabelle aller Methoden mit CRAP > 100 und 0% Coverage, absteigend nach CRAP.

Diese Ausgabe ist die Datenbasis für `prep-02-analysis.md`. Die Tabelle in der Konversation
behalten (nicht aus dem Kontext verlieren) — `prep-02` liest sie direkt.

## Weiter

→ `prep-02-analysis.md`
