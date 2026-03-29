#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# wait-for-it.sh — Wait for a TCP host:port to become available
# Vendored utility for container readiness checks

set -euo pipefail

HOST=""
PORT=""
TIMEOUT=30
QUIET=0

usage() {
  echo "Usage: $0 host:port [-t timeout] [-q]"
  exit 1
}

wait_for() {
  local start end
  start=$(date +%s)
  end=$((start + TIMEOUT))

  while true; do
    if (echo > /dev/tcp/"$HOST"/"$PORT") 2>/dev/null; then
      if [ "$QUIET" -eq 0 ]; then
        echo "$0: $HOST:$PORT is available"
      fi
      return 0
    fi

    local now
    now=$(date +%s)
    if [ "$now" -ge "$end" ]; then
      echo "$0: timeout after ${TIMEOUT}s waiting for $HOST:$PORT" >&2
      return 1
    fi

    sleep 1
  done
}

# Parse arguments
if [ $# -eq 0 ]; then
  usage
fi

IFS=: read -r HOST PORT <<< "$1"
shift

while [ $# -gt 0 ]; do
  case "$1" in
    -t) TIMEOUT="$2"; shift 2 ;;
    -q) QUIET=1; shift ;;
    *) usage ;;
  esac
done

if [ -z "$HOST" ] || [ -z "$PORT" ]; then
  usage
fi

wait_for
