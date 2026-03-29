#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
exec python3 "$(dirname "$0")/trace-report.py" "$@"
