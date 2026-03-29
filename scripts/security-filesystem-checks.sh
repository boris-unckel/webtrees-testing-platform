#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
# Dateisystem-Assertions für Sicherheitstests (Layer 3)
#
# Aufruf:
#   scripts/security-filesystem-checks.sh --pre-wizard   (vor Wizard-Durchlauf)
#   scripts/security-filesystem-checks.sh --post-wizard  (nach Wizard-Durchlauf)
#
# @see docs/security_plan.md Abschnitt 7.1

set -euo pipefail

CONTAINER="webtrees-security"
PASSED=0
FAILED=0
UPSTREAM=0
TOTAL=0

pass() {
    PASSED=$((PASSED + 1))
    TOTAL=$((TOTAL + 1))
    echo "  ✓ $1"
}

fail() {
    FAILED=$((FAILED + 1))
    TOTAL=$((TOTAL + 1))
    echo "  ✗ $1"
}

fail_upstream() {
    UPSTREAM=$((UPSTREAM + 1))
    TOTAL=$((TOTAL + 1))
    echo "  ⚠ $1 [UPSTREAM-BEFUND]"
}

pre_wizard_checks() {
    echo "[security-filesystem] Pre-Wizard-Checks"
    echo ""

    # @see SEC-H01
    if podman exec "${CONTAINER}" test -f /var/www/html/data/.htaccess; then
        pass "SEC-H01: data/.htaccess existiert"
    else
        fail "SEC-H01: data/.htaccess FEHLT"
    fi

    # @see SEC-H02
    if podman exec "${CONTAINER}" grep -q 'Require all denied' /var/www/html/data/.htaccess 2>/dev/null; then
        pass "SEC-H02: data/.htaccess enthält 'Require all denied'"
    else
        fail "SEC-H02: data/.htaccess enthält NICHT 'Require all denied'"
    fi

    # @see SEC-D01
    if podman exec "${CONTAINER}" test -f /var/www/html/data/index.php; then
        pass "SEC-D01: data/index.php existiert"
    else
        fail "SEC-D01: data/index.php FEHLT"
    fi

    # @see SEC-D02
    if podman exec "${CONTAINER}" grep -q "header('Location:" /var/www/html/data/index.php 2>/dev/null; then
        pass "SEC-D02: data/index.php enthält Redirect-Header"
    else
        fail "SEC-D02: data/index.php enthält KEINEN Redirect-Header"
    fi

    # @see SEC-PUB01
    if podman exec "${CONTAINER}" test -f /var/www/html/public/index.php; then
        pass "SEC-PUB01: public/index.php existiert"
    else
        fail "SEC-PUB01: public/index.php FEHLT"
    fi
}

post_wizard_checks() {
    echo "[security-filesystem] Post-Wizard-Checks"
    echo ""

    # @see SEC-WZ03 (Layer-3-Anteil)
    if podman exec "${CONTAINER}" test -f /var/www/html/data/config.ini.php; then
        pass "SEC-WZ03: config.ini.php existiert nach Wizard"
    else
        fail "SEC-WZ03: config.ini.php existiert NICHT nach Wizard"
        echo "         Überspringe SEC-C01–SEC-C03 (abhängig von config.ini.php)"
        return
    fi

    # @see SEC-C01
    local first_line
    first_line=$(podman exec "${CONTAINER}" head -1 /var/www/html/data/config.ini.php)
    if echo "${first_line}" | grep -q '; <?php return; ?>'; then
        pass "SEC-C01: config.ini.php hat PHP-Guard als erste Zeile"
    else
        fail "SEC-C01: config.ini.php hat KEINEN PHP-Guard als erste Zeile"
    fi

    # @see SEC-C02
    local config_keys
    config_keys=$(podman exec "${CONTAINER}" php -r "print_r(parse_ini_file('/var/www/html/data/config.ini.php'));" 2>/dev/null)
    local c02_ok=true
    for key in dbhost dbuser dbname; do
        if ! echo "${config_keys}" | grep -q "\[${key}\]"; then
            c02_ok=false
        fi
    done
    if [ "${c02_ok}" = true ]; then
        pass "SEC-C02: config.ini.php enthält DB-Credentials (dbhost, dbuser, dbname)"
    else
        fail "SEC-C02: config.ini.php enthält NICHT alle DB-Credentials"
    fi

    # @see SEC-C03
    local perms
    perms=$(podman exec "${CONTAINER}" stat -c '%a' /var/www/html/data/config.ini.php)
    local world_readable_bit="${perms:2:1}"
    if [ "${world_readable_bit}" -ge 4 ] 2>/dev/null; then
        fail_upstream "SEC-C03: config.ini.php ist world-readable (${perms}) — kein chmod im Wizard"
    else
        pass "SEC-C03: config.ini.php ist NICHT world-readable (${perms})"
    fi
}

summary() {
    echo ""
    local msg="[security-filesystem] Ergebnis: ${PASSED}/${TOTAL} bestanden, ${FAILED} fehlgeschlagen"
    if [ "${UPSTREAM}" -gt 0 ]; then
        msg="${msg}, ${UPSTREAM} Upstream-Befund(e)"
    fi
    echo "${msg}"
    if [ "${FAILED}" -gt 0 ]; then
        return 1
    fi
}

PHASE="${1:---pre-wizard}"

case "${PHASE}" in
    --pre-wizard)
        pre_wizard_checks
        summary
        ;;
    --post-wizard)
        post_wizard_checks
        summary
        ;;
    --all)
        pre_wizard_checks
        echo ""
        post_wizard_checks
        summary
        ;;
    *)
        echo "Usage: $0 --pre-wizard|--post-wizard|--all" >&2
        exit 1
        ;;
esac
