#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Klont webtrees-Upstream, wenn nicht bereits vorhanden.
# Idempotent: existierender Checkout wird nicht angetastet.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

WEBTREES_SOURCE="${WEBTREES_SOURCE:-${PROJECT_DIR}/upstream/webtrees}"
WEBTREES_REPO="${WEBTREES_REPO:-https://github.com/fisharebest/webtrees.git}"
WEBTREES_REF="${WEBTREES_REF:-main}"

if [ -d "${WEBTREES_SOURCE}" ]; then
    echo "webtrees-Source vorhanden: ${WEBTREES_SOURCE} (übersprungen)"
    exit 0
fi

echo "webtrees-Source klonen..."
echo "  Repo: ${WEBTREES_REPO}"
echo "  Ref:  ${WEBTREES_REF}"
echo "  Ziel: ${WEBTREES_SOURCE}"

mkdir -p "$(dirname "${WEBTREES_SOURCE}")"
git clone --branch "${WEBTREES_REF}" "${WEBTREES_REPO}" "${WEBTREES_SOURCE}"

echo "Clone abgeschlossen: ${WEBTREES_SOURCE}"
