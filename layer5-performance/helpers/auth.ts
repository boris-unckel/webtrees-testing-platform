// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Authentifizierungs-Konstanten fuer Performance-Tests.
 * Passwoerter werden aus Umgebungsvariablen gelesen (generiert durch scripts/generate-passwords.sh).
 */

function requireEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Umgebungsvariable ${name} nicht gesetzt — wurde 'make setup' ausgefuehrt?`);
  }
  return value;
}

/** Admin-Passwort (User 'admin', angelegt in setup-webtrees.sh) */
export const ADMIN_PASSWORD = requireEnv('WEBTREES_ADMIN_PASSWORD');
