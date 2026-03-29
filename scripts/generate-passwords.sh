#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# generate-passwords.sh — Generiert zufaellige Passwoerter und patcht .env
# Wird automatisch durch `make up` aufgerufen.

set -euo pipefail

ENV_FILE="${1:-.env}"
ENV_EXAMPLE=".env.example"

# Falls .env nicht existiert: aus .env.example erzeugen
if [[ ! -f "${ENV_FILE}" ]]; then
    cp "${ENV_EXAMPLE}" "${ENV_FILE}"
    echo ".env aus .env.example erzeugt."
fi

PASSWORD_KEYS=(
    MYSQL_ROOT_PASSWORD
    MYSQL_PASSWORD
    MYSQL_SECURITY_ROOT_PASSWORD
    MYSQL_SECURITY_PASSWORD
    WEBTREES_ADMIN_PASSWORD
    WEBTREES_TEST_USER_PASSWORD
)

generate_password() {
    openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 24
}

GENERATED=0
for key in "${PASSWORD_KEYS[@]}"; do
    if grep -q "^${key}=" "${ENV_FILE}"; then
        # Key existiert — pruefen ob leer
        if grep -q "^${key}=$" "${ENV_FILE}"; then
            pw="$(generate_password)"
            sed -i "s/^${key}=.*/${key}=${pw}/" "${ENV_FILE}"
            echo "  ${key} generiert."
            GENERATED=$((GENERATED + 1))
        fi
    else
        # Key existiert nicht — hinzufuegen und generieren
        pw="$(generate_password)"
        echo "${key}=${pw}" >> "${ENV_FILE}"
        echo "  ${key} hinzugefuegt und generiert."
        GENERATED=$((GENERATED + 1))
    fi
done

if [[ "${GENERATED}" -eq 0 ]]; then
    echo "Alle Passwoerter bereits gesetzt — keine Aenderung."
else
    echo "${GENERATED} Passwoerter generiert."
fi
