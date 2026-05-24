#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Liest eine Clover-XML (PHPUnit `--coverage-clover`) und aggregiert Metriken.
# Schreibt key=value-Zeilen nach stdout (eine Metrik pro Zeile).
#
# Aufruf:
#   clover_aggregate.sh totals <xml>
#     Gibt die Wurzel-Metriken `/coverage/project/metrics` zurueck.
#
#   clover_aggregate.sh by-prefix <xml> <prefix>
#     Summiert Metriken ueber alle `<file>`, deren `name`-Attribut mit
#     `<prefix>` beginnt. Class-level-Metriken werden uebersprungen.
#     Beispiel-Prefix fuer L2-Snapshot 2026-05-24: `/var/www/html/app/Http/`.
#
#   clover_aggregate.sh files-csv <xml>
#     Schreibt CSV mit einer Zeile pro `<file>`:
#       file,statements,coveredstatements,methods,coveredmethods,elements,coveredelements
#
# Vorraussetzungen: `xmllint` muss verfuegbar sein (Validierung der XML), die
# Auswertung selbst laeuft mit `awk` zeilenweise — keine XPath-Abfrage pro
# Attribut, damit die Laufzeit bei 3-MB-XMLs unter einer Sekunde bleibt.

set -euo pipefail

USAGE="Usage: $(basename "$0") <command> <xml> [args]
Commands:
  totals <xml>
  by-prefix <xml> <prefix>
  files-csv <xml>"
readonly USAGE

require_xml() {
  local xml="$1"
  if [[ ! -f "$xml" ]]; then
    printf 'Error: XML file not found: %s\n' "$xml" >&2
    exit 1
  fi
  if ! xmllint --noout "$xml" 2>/dev/null; then
    printf 'Error: not valid XML: %s\n' "$xml" >&2
    exit 1
  fi
}

cmd_totals() {
  local xml="$1"
  xmllint --xpath '/coverage/project/metrics/@*' "$xml" 2>/dev/null \
    | grep -oE '[a-zA-Z_]+="[^"]*"' \
    | sed 's/="/=/; s/"$//'
}

cmd_by_prefix() {
  local xml="$1"
  local prefix="$2"
  awk -v prefix="$prefix" '
    /<file name="/ {
      in_file = 1
      in_class = 0
      match($0, /name="[^"]*"/)
      fname = substr($0, RSTART + 6, RLENGTH - 7)
      match_now = (index(fname, prefix) == 1) ? 1 : 0
      if (match_now) files_matched++
    }
    /<\/file>/ { in_file = 0; match_now = 0 }
    /<class / { in_class = 1 }
    /<\/class>/ { in_class = 0 }
    /<metrics / && in_file && !in_class && match_now {
      line = $0
      while (match(line, /[a-z]+="[0-9]+"/)) {
        token = substr(line, RSTART, RLENGTH)
        eq = index(token, "=")
        key = substr(token, 1, eq - 1)
        val = substr(token, eq + 2, length(token) - eq - 2)
        sum[key] += val + 0
        line = substr(line, RSTART + RLENGTH)
      }
    }
    END {
      printf "files_matched=%d\n", files_matched + 0
      n = asorti(sum, keys)
      for (i = 1; i <= n; i++) {
        printf "%s=%d\n", keys[i], sum[keys[i]]
      }
    }
  ' "$xml"
}

cmd_files_csv() {
  local xml="$1"
  awk '
    BEGIN {
      print "file,statements,coveredstatements,methods,coveredmethods,elements,coveredelements"
    }
    /<file name="/ {
      in_file = 1
      in_class = 0
      match($0, /name="[^"]*"/)
      fname = substr($0, RSTART + 6, RLENGTH - 7)
      stmts = 0; cstmts = 0; mthds = 0; cmthds = 0
      elms = 0; celms = 0
    }
    /<\/file>/ {
      if (in_file) {
        printf "%s,%d,%d,%d,%d,%d,%d\n",
          fname, stmts, cstmts, mthds, cmthds, elms, celms
      }
      in_file = 0
    }
    /<class / { in_class = 1 }
    /<\/class>/ { in_class = 0 }
    /<metrics / && in_file && !in_class {
      line = $0
      while (match(line, /[a-z]+="[0-9]+"/)) {
        token = substr(line, RSTART, RLENGTH)
        eq = index(token, "=")
        key = substr(token, 1, eq - 1)
        val = substr(token, eq + 2, length(token) - eq - 2)
        attrs[key] = val + 0
        line = substr(line, RSTART + RLENGTH)
      }
      stmts = attrs["statements"] + 0
      cstmts = attrs["coveredstatements"] + 0
      mthds = attrs["methods"] + 0
      cmthds = attrs["coveredmethods"] + 0
      elms = attrs["elements"] + 0
      celms = attrs["coveredelements"] + 0
      delete attrs
    }
  ' "$xml"
}

main() {
  if [[ $# -lt 2 ]]; then
    printf '%s\n' "$USAGE" >&2
    exit 2
  fi

  local cmd="$1"
  local xml="$2"
  shift 2

  require_xml "$xml"

  case "$cmd" in
    totals)
      cmd_totals "$xml"
      ;;
    by-prefix)
      if [[ $# -ne 1 ]]; then
        printf '%s\n' "$USAGE" >&2
        exit 2
      fi
      cmd_by_prefix "$xml" "$1"
      ;;
    files-csv)
      cmd_files_csv "$xml"
      ;;
    *)
      printf '%s\n' "$USAGE" >&2
      exit 2
      ;;
  esac
}

main "$@"
