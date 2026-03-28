# Prompt — Implementierung Sicherheitstests

Implementiere den Plan in `docs/security_plan.md`, Phasen S1–S6.

## Regeln

1. **Status tracken:** Aktualisiere bei jeder Statusänderung die Phasen-Übersicht, die Phase-Detail-Tabelle und die Status-Spalte in der Feature-Matrix (Abschnitt 4.2) in `docs/security_plan.md`.
2. **Kleinteilig:** Jede Phase einzeln implementieren, testen. Keine Phase überspringen.
3. **Security-Läufe:** `make test-security` (oder Teilaufrufe) jederzeit zum Testen und Bugfixen erlaubt.
4. **Upstream-Befunde:** Test bleibt rot, Annotation im Code, Status in Feature-Matrix auf `Rot (Upstream-Befund)`.
5. **Gesamttest am Ende:** Nach S5 einen vollständigen Testlauf aller Teststufen durchführen (`make test-unit && make test-integration && make test-e2e && make test-security`). Fehler fixen, bis alles grün ist. Erst danach S6.
6. **Phase S6 (Doku) erst am Ende:** Bigpicture-Update erst, wenn S1–S5 verifiziert sind und der Gesamttest steht.
7. **Keine commits:** git commit wird manuell ausgelöst.
7. **Kontext:** `docs/testing-bigpicture.md` für bestehende Strukturen, `CLAUDE.md` für Infrastruktur.
