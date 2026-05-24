#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Erhebt pro Playwright-Spec: lines, tests, each_loops, expects, phpdoc_ep,
# phpdoc_sub, Hybrid-V2-Klassifikation. Schreibt CSV nach stdout.
#
# Aufruf:
#   gap_inventory_ts.sh <base-dir>
#     <base-dir>  Verzeichnis mit *.spec.ts-Dateien (rekursiv). Pfade in der
#                 CSV sind relativ zu diesem Verzeichnis.
#
# Zaehl-Konvention:
#   tests       Zeilen, die mit `test(` beginnen (Top-Level, eingerueckt OK).
#   each_loops  Zeilen, die mit `test.each(` beginnen.
#   expects     Vorkommen von `expect(` (inkl. `await expect(`).
#
# Klassifikation (V2-Hybrid, analog zum PHP-Skript, siehe
# docs/coverage-runs/historical/2026-04-11_gap-analyse-fork.md §2). Statt
# `providers` zaehlt hier `each_loops`, statt `methods` zaehlen `tests` und
# statt `assertions` zaehlen `expects`. density = expects/tests.
#
# Interne Variable `psub` (statt `sub`) — `sub` ist ein awk-Schluesselwort.

set -euo pipefail

USAGE="Usage: $(basename "$0") <base-dir>"
readonly USAGE

classify_ts() {
  local tests="$1"
  local each_loops="$2"
  local lines="$3"
  local expects="$4"
  local ep="$5"
  local psub="$6"

  if (( ep > 0 )); then
    printf 'EP-complete'
    return
  fi
  if (( psub > 0 )); then
    printf 'Substantial'
    return
  fi
  if (( tests == 0 )); then
    printf 'Stub'
    return
  fi

  if (( each_loops >= 3 )); then
    printf 'EP-complete'
    return
  fi
  if (( tests >= 10 )) && \
      awk -v e="$expects" -v t="$tests" \
        'BEGIN { exit (e/t >= 2.0 ? 0 : 1) }'; then
    printf 'EP-complete'
    return
  fi
  if (( tests >= 3 )); then
    if awk -v e="$expects" -v t="$tests" \
        'BEGIN { exit (e/t >= 2.0 ? 0 : 1) }'; then
      printf 'Substantial'
      return
    fi
    if awk -v l="$lines" -v e="$expects" -v t="$tests" \
        'BEGIN { exit (l/t >= 15 && e/t >= 1.0 ? 0 : 1) }'; then
      printf 'Substantial'
      return
    fi
  fi
  if (( tests >= 2 )) && \
      awk -v l="$lines" -v e="$expects" -v t="$tests" \
        'BEGIN { exit (e/t >= 1.0 || l/t >= 10 ? 0 : 1) }'; then
    printf 'Smoke'
    return
  fi
  printf 'Stub'
}

process_file_ts() {
  local f="$1"
  local rel="$2"

  local stats
  stats=$(awk '
    BEGIN {
      lines = 0; tests = 0; each_loops = 0
      expects = 0; ep = 0; psub = 0
    }
    {
      lines++
      if ($0 ~ /^[[:space:]]*test\.each[[:space:]]*\(/) {
        each_loops++
      } else if ($0 ~ /^[[:space:]]*test[[:space:]]*\(/) {
        tests++
      }
      expects += gsub(/expect\(/, "&")
      if ($0 ~ /@ep([^A-Za-z]|$)/) ep = 1
      if ($0 ~ /@substantial([^A-Za-z]|$)/) psub = 1
    }
    END {
      printf "%d %d %d %d %d %d",
        lines, tests, each_loops, expects, ep, psub
    }
  ' "$f")

  local lines tests each_loops expects ep psub
  read -r lines tests each_loops expects ep psub <<<"$stats"

  local density_csv
  if (( tests == 0 )); then
    density_csv='0.00'
  else
    density_csv=$(awk -v e="$expects" -v t="$tests" \
      'BEGIN { printf "%.2f", e/t }')
  fi

  local cls
  cls=$(classify_ts "$tests" "$each_loops" "$lines" "$expects" \
                    "$ep" "$psub")

  printf '%s,%s,%s,%s,%s,%s,%s,%s,%s\n' \
    "$rel" "$lines" "$tests" "$each_loops" "$expects" \
    "$density_csv" "$ep" "$psub" "$cls"
}

main() {
  if [[ $# -ne 1 ]]; then
    printf '%s\n' "$USAGE" >&2
    exit 2
  fi

  local base_dir="$1"
  base_dir="${base_dir%/}"

  if [[ ! -d "$base_dir" ]]; then
    printf 'Error: %s is not a directory\n' "$base_dir" >&2
    exit 1
  fi

  printf 'file,lines,tests,each_loops,expects,density,phpdoc_ep,phpdoc_sub,classification\n'

  local files=()
  while IFS= read -r line; do
    files+=("$line")
  done < <(find "$base_dir" -type f -name '*.spec.ts' | sort)

  local f rel
  for f in "${files[@]}"; do
    rel="${f#"$base_dir/"}"
    process_file_ts "$f" "$rel"
  done
}

main "$@"
