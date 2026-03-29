#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

LAYER="${1:?Fehler: Layer angeben (layer3, layer4, layer5)}"
CONTAINER="${2:-mysql}"
TARGET_DIR="artifacts/${LAYER}/perfschema"
[[ -n "${MYSQL_ROOT_PASSWORD:-}" ]] || { echo "FEHLER: MYSQL_ROOT_PASSWORD nicht gesetzt" >&2; exit 1; }
MYSQL_DATABASE="${MYSQL_DATABASE:-webtrees_test}"

mkdir -p "$TARGET_DIR"

run_query() {
  podman-compose exec "$CONTAINER" mysql -u root \
    -p"$MYSQL_ROOT_PASSWORD" --batch --raw --skip-column-names -e "$1"
}

echo "=== PerfSchema-Extraktion fuer ${LAYER} ==="

# statements_by_digest.json — Top-50 Queries
echo "  statements_by_digest.json..."
run_query "
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'schema', SCHEMA_NAME,
  'digest', DIGEST,
  'digest_text', DIGEST_TEXT,
  'count', COUNT_STAR,
  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2),
  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000, 2),
  'max_ms', ROUND(MAX_TIMER_WAIT/1000000000, 2),
  'p95_ms', ROUND(QUANTILE_95/1000000000, 2),
  'p99_ms', ROUND(QUANTILE_99/1000000000, 2),
  'rows_examined', SUM_ROWS_EXAMINED,
  'rows_sent', SUM_ROWS_SENT,
  'full_scans', SUM_SELECT_SCAN,
  'no_index', SUM_NO_INDEX_USED,
  'tmp_disk_tables', SUM_CREATED_TMP_DISK_TABLES,
  'lock_time_ms', ROUND(SUM_LOCK_TIME/1000000000, 2),
  'sample_text', LEFT(QUERY_SAMPLE_TEXT, 500),
  'first_seen', FIRST_SEEN,
  'last_seen', LAST_SEEN
))
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = '${MYSQL_DATABASE}'
  AND DIGEST_TEXT IS NOT NULL
ORDER BY SUM_TIMER_WAIT DESC
LIMIT 50
" > "${TARGET_DIR}/statements_by_digest.json"

# table_io_waits.json
echo "  table_io_waits.json..."
run_query "
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'table_name', OBJECT_NAME,
  'count_star', COUNT_STAR,
  'count_read', COUNT_READ,
  'count_write', COUNT_WRITE,
  'count_fetch', COUNT_FETCH,
  'count_insert', COUNT_INSERT,
  'count_update', COUNT_UPDATE,
  'count_delete', COUNT_DELETE,
  'total_wait_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2)
))
FROM performance_schema.table_io_waits_summary_by_table
WHERE OBJECT_SCHEMA = '${MYSQL_DATABASE}'
ORDER BY SUM_TIMER_WAIT DESC
" > "${TARGET_DIR}/table_io_waits.json"

# stages_global.json
echo "  stages_global.json..."
run_query "
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'event_name', EVENT_NAME,
  'count', COUNT_STAR,
  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2),
  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000, 2)
))
FROM performance_schema.events_stages_summary_global_by_event_name
WHERE COUNT_STAR > 0
ORDER BY SUM_TIMER_WAIT DESC
" > "${TARGET_DIR}/stages_global.json"

# transactions_global.json
echo "  transactions_global.json..."
run_query "
SELECT JSON_ARRAYAGG(JSON_OBJECT(
  'event_name', EVENT_NAME,
  'count', COUNT_STAR,
  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000, 2),
  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000, 2)
))
FROM performance_schema.events_transactions_summary_global_by_event_name
WHERE COUNT_STAR > 0
ORDER BY SUM_TIMER_WAIT DESC
" > "${TARGET_DIR}/transactions_global.json"

# summary.txt — Menschenlesbare Zusammenfassung
echo "  summary.txt..."
{
  echo "=== PerfSchema Summary: ${LAYER} ($(date -Iseconds)) ==="
  echo ""
  echo "--- Top-10 Queries by Total Latency ---"
  run_query "
  SELECT CONCAT(
    ROW_NUMBER() OVER (ORDER BY SUM_TIMER_WAIT DESC), '. ',
    LEFT(DIGEST_TEXT, 80), '  ',
    'avg=', ROUND(AVG_TIMER_WAIT/1000000000, 2), 'ms  ',
    'calls=', COUNT_STAR, '  ',
    'rows=', SUM_ROWS_EXAMINED
  )
  FROM performance_schema.events_statements_summary_by_digest
  WHERE SCHEMA_NAME = '${MYSQL_DATABASE}' AND DIGEST_TEXT IS NOT NULL
  ORDER BY SUM_TIMER_WAIT DESC
  LIMIT 10
  "
  echo ""
  echo "--- Top-5 Tables by I/O Wait ---"
  run_query "
  SELECT CONCAT(
    ROW_NUMBER() OVER (ORDER BY SUM_TIMER_WAIT DESC), '. ',
    OBJECT_NAME, '  ',
    'reads=', COUNT_READ, '  ',
    'writes=', COUNT_WRITE, '  ',
    'total_wait=', ROUND(SUM_TIMER_WAIT/1000000000, 2), 'ms'
  )
  FROM performance_schema.table_io_waits_summary_by_table
  WHERE OBJECT_SCHEMA = '${MYSQL_DATABASE}'
  ORDER BY SUM_TIMER_WAIT DESC
  LIMIT 5
  "
  echo ""
  echo "--- Warnungen ---"
  FULL_SCANS=$(run_query "
  SELECT COUNT(*) FROM performance_schema.events_statements_summary_by_digest
  WHERE SCHEMA_NAME = '${MYSQL_DATABASE}' AND SUM_SELECT_SCAN > 0 AND DIGEST_TEXT IS NOT NULL
  ")
  NO_INDEX=$(run_query "
  SELECT COUNT(*) FROM performance_schema.events_statements_summary_by_digest
  WHERE SCHEMA_NAME = '${MYSQL_DATABASE}' AND SUM_NO_INDEX_USED > 0 AND DIGEST_TEXT IS NOT NULL
  ")
  TMP_DISK=$(run_query "
  SELECT COUNT(*) FROM performance_schema.events_statements_summary_by_digest
  WHERE SCHEMA_NAME = '${MYSQL_DATABASE}' AND SUM_CREATED_TMP_DISK_TABLES > 0 AND DIGEST_TEXT IS NOT NULL
  ")
  echo "Full Table Scans: ${FULL_SCANS} Queries"
  echo "No-Index Queries: ${NO_INDEX} Queries"
  echo "Temp-Tabellen auf Disk: ${TMP_DISK} Queries"
} > "${TARGET_DIR}/summary.txt"

echo "=== PerfSchema-Extraktion abgeschlossen: ${TARGET_DIR}/ ==="
