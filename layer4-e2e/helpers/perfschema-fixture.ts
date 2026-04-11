// SPDX-License-Identifier: AGPL-3.0-or-later
import { test as otelBase, expect } from './otel-fixture';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

function mysqlRoot(sql: string): void {
  execSync(
    `mysql -h "${process.env.MYSQL_HOST ?? 'mysql'}" -u root` +
    ` -p"${process.env.MYSQL_ROOT_PASSWORD}" -e "${sql}"`,
    { stdio: 'pipe' }
  );
}

function mysqlQuery(host: string, pwd: string, sql: string): string {
  return execSync(
    `mysql -h "${host}" -u root -p"${pwd}" --batch --raw --skip-column-names -e "${sql}"`,
    { stdio: 'pipe' }
  ).toString().trim();
}

function extractPerfschema(dir: string): void {
  const host = process.env.MYSQL_HOST ?? 'mysql';
  const pwd  = process.env.MYSQL_ROOT_PASSWORD ?? '';
  const db   = process.env.MYSQL_DATABASE ?? 'webtrees_test';

  // Text-Protokoll-Queries (connection setup, USE, SET NAMES, BEGIN/COMMIT)
  const stmts = mysqlQuery(host, pwd,
    `SELECT JSON_ARRAYAGG(JSON_OBJECT(` +
    `  'digest_text', DIGEST_TEXT,` +
    `  'count', COUNT_STAR,` +
    `  'avg_ms', ROUND(AVG_TIMER_WAIT/1000000000,2),` +
    `  'total_ms', ROUND(SUM_TIMER_WAIT/1000000000,2),` +
    `  'rows_examined', SUM_ROWS_EXAMINED,` +
    `  'full_scans', SUM_SELECT_SCAN,` +
    `  'no_index', SUM_NO_INDEX_USED` +
    `)) FROM performance_schema.events_statements_summary_by_digest` +
    ` WHERE SCHEMA_NAME = '${db}' AND DIGEST_TEXT IS NOT NULL` +
    ` ORDER BY SUM_TIMER_WAIT DESC LIMIT 30`
  );

  // Tabellen-I/O (Storage-Engine-Ebene, erfasst auch Binary-Protocol-Prepared-Statements)
  const tableIo = mysqlQuery(host, pwd,
    `SELECT JSON_ARRAYAGG(JSON_OBJECT(` +
    `  'table_name', OBJECT_NAME,` +
    `  'count_read', COUNT_READ,` +
    `  'count_write', COUNT_WRITE,` +
    `  'count_fetch', COUNT_FETCH,` +
    `  'count_insert', COUNT_INSERT,` +
    `  'count_update', COUNT_UPDATE,` +
    `  'count_delete', COUNT_DELETE,` +
    `  'total_wait_ms', ROUND(SUM_TIMER_WAIT/1000000000,2)` +
    `)) FROM performance_schema.table_io_waits_summary_by_table` +
    ` WHERE OBJECT_SCHEMA = '${db}' AND COUNT_STAR > 0` +
    ` ORDER BY SUM_TIMER_WAIT DESC LIMIT 30`
  );

  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'statements.json'), stmts || '[]');
  fs.writeFileSync(path.join(dir, 'table_io_waits.json'), tableIo || '[]');
}

export const test = otelBase.extend<{ _perfschema: void }>({
  _perfschema: [async ({}, use, testInfo) => {
    mysqlRoot(
      'TRUNCATE TABLE performance_schema.events_statements_summary_by_digest; ' +
      'TRUNCATE TABLE performance_schema.table_io_waits_summary_by_table;'
    );

    await use();

    const safeId = testInfo.title.replace(/[^a-zA-Z0-9_.-]/g, '_');
    extractPerfschema(`/artifacts/layer4/perfschema/per-test/${safeId}`);
  }, { auto: true }],
});

export { expect };
