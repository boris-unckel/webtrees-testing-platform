<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# improve-layer3

Iterativer Rückbau stiller L3-Tests (Skip / `class_exists`-Tautologie / `assertTrue(true)` / Phantom-Assertion). Quelle der Liste: Spuernasen-Sweep am Ende von `layer3-integration/run.sh` → `artifacts/layer3/silent-tests-sweep.txt`.

## Artefakte

| Datei | Zweck |
|---|---|
| `plan.md` | Statustracker, eine Zeile pro Sweep-Treffer. |
| `audit.log` | Append-only Aktions-Log (`Timestamp TASK-ID Aktion Ergebnis`). Kein Repo-Zustand, keine Akkumulation. |
| `.claude/agents/improve-layer3-worker.md` | Worker-Agent für die Umsetzung (genau ein Task pro Aufruf). |

## Wiedereinstiegsprompt — für den Orchestrator (Hauptsession)

> Hauptsession arbeitet kontextschonend: lädt nur `plan.md` (Tabelle), stoßt pro offenem Task einen Worker an, verifiziert das Ergebnis, geht weiter. **Keine Git-Operationen — weder durch den Worker noch durch die Hauptsession.** Der Working-Tree akkumuliert über die gesamte Iteration; der User reviewt und committet ganz am Ende manuell.
>
> 1. `docs/improve-layer3/plan.md` einlesen, **erste Zeile mit Status `offen`** wählen — sei das `L3SP-NNN`.
> 2. Sicherstellen, dass im Container kein PHPUnit läuft:
>    ```
>    podman-compose exec webtrees pgrep -a -f phpunit
>    ```
>    leer = ok. Falls nicht: warten bis Ende oder gezielt killen (siehe `CLAUDE.md`).
> 3. Worker anstoßen:
>    ```
>    Agent({
>      subagent_type: "improve-layer3-worker",
>      description:   "L3SP-NNN",
>      prompt:        "Behandle L3SP-NNN gemäß deiner Agent-Spec."
>    })
>    ```
> 4. JSON-Return prüfen:
>    - `{"ok": true, "task": "L3SP-NNN", "files_changed": [...], …}` → Worker hat `plan.md`-Status auf `erledigt` (oder `akzeptiert`/`false_positive`) gesetzt und `audit.log` appendet. Direkt nächste offene Zeile. **Kein Commit.**
>    - `{"ok": false, "phase": "<…>", "reason": "<…>"}` → letzte Zeilen `audit.log` einlesen, Diagnose, dann entweder Folge-Prompt an Worker oder Status manuell auf `blockiert` setzen.
> 5. Wiederholen bis keine Zeile mehr `offen` ist.
> 6. Abschluss (durch den User, **nicht** durch die Hauptsession):
>    - Voll-Lauf `make test-integration` (`run_in_background: true`). Sweep-Footer-Treffer muss ≤ `akzeptiert + false_positive` sein.
>    - Manuelles Review des akkumulierten Working-Trees (`git status`, `git diff`).
>    - Manueller Commit (GPG-signiert, Locale de_DE, ggf. nach Themen gruppiert per `git add -p`).

## Worker-Vertrag (Kurzfassung)

- Eingabe: **genau eine** Task-ID
- Verarbeitet: **genau einen** Sweep-Treffer
- Schreibt: Testdatei (Edit), `plan.md` (nur Statuszelle der eigenen Zeile), `audit.log` (append)
- Liest: SUT in `./upstream/webtrees/app/...`, betroffene Testdatei, ggf. Test-Helfer
- Lehnt ab: SUT-Änderungen, Mehrfach-Tasks, Voll-Lauf, Parallel-Lauf, **alle Git-Operationen**
- Vollständige Spec: `.claude/agents/improve-layer3-worker.md`

## Regelwerk-Quellen

- Workflow-Muster (Mocks, Patterns, Phasen): `docs/wf_test-iteration_guide.md`
- Container-Aufrufe, Locale, GPG, SPDX: `CLAUDE.md`
- Memo "Session-Verantwortung = gesamtes git diff": Bestand-Probleme mit Bezug zum Task fixt der Worker im selben Commit mit; ohne Bezug → audit.log-Eintrag `follow_up <kurzbeschreibung>` und ein neuer Plan-Eintrag durch den Orchestrator (kein Worker-Scope).

## Beendigungskriterium

Wenn alle Zeilen einen Endzustand (`erledigt|akzeptiert|false_positive|blockiert`) tragen, ist die Iteration durch. Dann (durch den User):

1. einmalig `make test-integration` voll, Sweep-Footer kontrollieren,
2. Working-Tree manuell reviewen und committen,
3. Dokumentations-Abschluss (`docs/tds_coverage_ref.md`, `docs/tp_ratchet_spec.md`) in einer separaten Sitzung — kein Worker-Scope.

## Re-Generierung des Plans

Wenn neue stille Tests hinzukommen oder die Sweep-Mechanik verfeinert wird, `plan.md` aus dem aktuellen Sweep neu erzeugen. Der Worker editiert nur eigene Statuszellen; die ID-Spalte und die Reihenfolge sind beim Re-Generieren stabil neu zu vergeben.
