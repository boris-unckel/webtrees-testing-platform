#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

[[ -n "${MYSQL_ROOT_PASSWORD}" ]] || { echo "FEHLER: MYSQL_ROOT_PASSWORD nicht gesetzt" >&2; exit 1; }

CONTAINER="${1:-mysql}"
podman-compose exec -e MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" "$CONTAINER" mysql -u root -e "
  TRUNCATE TABLE performance_schema.events_statements_summary_by_digest;
  TRUNCATE TABLE performance_schema.events_stages_summary_global_by_event_name;
  TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;
  TRUNCATE TABLE performance_schema.events_transactions_summary_global_by_event_name;
"
