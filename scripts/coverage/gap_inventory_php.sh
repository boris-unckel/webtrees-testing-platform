#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Erhebt pro PHP-Testdatei: lines, methods, providers, assertions,
# phpdoc_ep, phpdoc_sub, Hybrid-V2-Klassifikation. Schreibt CSV nach stdout.
#
# Aufruf:
#   gap_inventory_php.sh <base-dir>
#     <base-dir>  Verzeichnis mit *Test.php-Dateien (rekursiv). Pfade in der
#                 CSV sind relativ zu diesem Verzeichnis.
#
# Klassifikation (V2-Hybrid, dokumentiert in
# docs/coverage-runs/historical/2026-04-11_gap-analyse-fork.md §2):
#   PHPDoc-Override: @ep -> EP-complete, @substantial -> Substantial.
#   Metrisch (in Prüfreihenfolge):
#     EP-complete: providers>=3 ODER (methods>=10 UND density>=2.0)
#     Substantial: (methods>=3 UND density>=2.0)
#                  ODER (methods>=3 UND lines/methods>=15 UND density>=1.0)
#     Smoke:       methods>=2 UND (density>=1.0 ODER lines/methods>=10)
#     Stub:        sonst (inkl. methods==0)
#
# density = assertions/methods.

set -euo pipefail

USAGE="Usage: $(basename "$0") <base-dir>"
readonly USAGE

classify_php() {
  local methods="$1"
  local providers="$2"
  local lines="$3"
  local assertions="$4"
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
  if (( methods == 0 )); then
    printf 'Stub'
    return
  fi

  if (( providers >= 3 )); then
    printf 'EP-complete'
    return
  fi
  if (( methods >= 10 )) && \
      awk -v a="$assertions" -v m="$methods" \
        'BEGIN { exit (a/m >= 2.0 ? 0 : 1) }'; then
    printf 'EP-complete'
    return
  fi
  if (( methods >= 3 )); then
    if awk -v a="$assertions" -v m="$methods" \
        'BEGIN { exit (a/m >= 2.0 ? 0 : 1) }'; then
      printf 'Substantial'
      return
    fi
    if awk -v l="$lines" -v a="$assertions" -v m="$methods" \
        'BEGIN { exit (l/m >= 15 && a/m >= 1.0 ? 0 : 1) }'; then
      printf 'Substantial'
      return
    fi
  fi
  if (( methods >= 2 )) && \
      awk -v l="$lines" -v a="$assertions" -v m="$methods" \
        'BEGIN { exit (a/m >= 1.0 || l/m >= 10 ? 0 : 1) }'; then
    printf 'Smoke'
    return
  fi
  printf 'Stub'
}

process_file_php() {
  local f="$1"
  local rel="$2"

  local stats
  stats=$(awk '
    BEGIN {
      lines = 0; methods = 0; providers = 0
      assertions = 0; ep = 0; psub = 0; pending = 0
    }
    {
      lines++
      if ($0 ~ /^[[:space:]]*#\[Test\]/ ||
          $0 ~ /^[[:space:]]*#\[Test\(/) {
        pending = 1
      } else if ($0 ~ /^[[:space:]]*(public|protected|private)?[[:space:]]*(static[[:space:]]+)?function[[:space:]]+/) {
        if (pending || $0 ~ /function[[:space:]]+test[A-Za-z_]/) {
          methods++
        }
        pending = 0
      }
      if ($0 ~ /^[[:space:]]*#\[DataProvider/) providers++
      assertions += gsub(/(\$this->|self::|static::)[[:space:]]*assert[A-Z]/, "&")
      if ($0 ~ /@ep([^A-Za-z]|$)/) ep = 1
      if ($0 ~ /@substantial([^A-Za-z]|$)/) psub = 1
    }
    END {
      printf "%d %d %d %d %d %d",
        lines, methods, providers, assertions, ep, psub
    }
  ' "$f")

  local lines methods providers assertions ep psub
  read -r lines methods providers assertions ep psub <<<"$stats"

  local density_csv
  if (( methods == 0 )); then
    density_csv='0.00'
  else
    density_csv=$(awk -v a="$assertions" -v m="$methods" \
      'BEGIN { printf "%.2f", a/m }')
  fi

  local cls
  cls=$(classify_php "$methods" "$providers" "$lines" "$assertions" \
                     "$ep" "$psub")

  printf '%s,%s,%s,%s,%s,%s,%s,%s,%s\n' \
    "$rel" "$lines" "$methods" "$providers" "$assertions" \
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

  printf 'file,lines,methods,providers,assertions,density,phpdoc_ep,phpdoc_sub,classification\n'

  local files=()
  while IFS= read -r line; do
    files+=("$line")
  done < <(find "$base_dir" -type f -name '*Test.php' \
                ! -name '*TestCase.php' | sort)

  local f rel
  for f in "${files[@]}"; do
    rel="${f#"$base_dir/"}"
    process_file_php "$f" "$rel"
  done
}

main "$@"
