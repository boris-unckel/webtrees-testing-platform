# SPDX-License-Identifier: AGPL-3.0-or-later

# webtrees Test-Stack — Makefile
# Alle Targets verwenden podman-compose

# Strikte Shell-Semantik fuer alle Recipes: jeder Pipeline-Fehler propagiert
# (Ende der tee-Pipeline-Maskierung), unset-Variablen brechen sofort ab,
# Fehler in nicht-konditionalen Befehlen brechen sofort ab. Bewusster
# Breaking Change: bestehende Recipes muessen mit '|| true' bzw. '-' robust
# bleiben, statt sich auf nachsichtige Default-Shell-Semantik zu verlassen.
SHELL := /bin/bash
.SHELLFLAGS := -euo pipefail -c

-include .env
export

COMPOSE = podman-compose -f compose.yaml
COMPOSE_DEBUG = podman-compose -f compose.yaml --profile debug
COMPOSE_SECURITY = podman-compose -f compose.yaml --profile security

WEBTREES_SOURCE ?= ./upstream/webtrees
TRIVY_VERSION   ?= 0.71.0
TRIVY_IMAGE      = ghcr.io/aquasecurity/trivy:$(TRIVY_VERSION)

.PHONY: help clone-upstream normalize-source-perms generate-passwords generate-fixtures up _compose-up up-debug _compose-up-debug down clean setup test-all test-static test-unit test-integration test-integration-quick test-integration-security test-e2e test-e2e-quick test-performance perfschema-truncate perfschema-extract trace-report crap-report security-build test-security _security-run security-up _security-compose-up security-down security-clean logs status

help: ## Hilfe anzeigen
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

clone-upstream: ## webtrees-Source klonen (falls nicht vorhanden), git core.sharedRepository=all setzen
	scripts/clone-upstream.sh

normalize-source-perms: clone-upstream ## webtrees-Source world-readable machen (idempotent)
	scripts/normalize-source-perms.sh

generate-passwords: ## Passwoerter in .env generieren (falls leer)
	scripts/generate-passwords.sh

generate-fixtures: ## Generierte Fixtures aus Templates erzeugen (privacy-test.ged)
	scripts/generate-privacy-fixture.sh

up: normalize-source-perms generate-passwords generate-fixtures ## Stack starten (alle Container)
	@$(MAKE) --no-print-directory _compose-up

_compose-up:
	$(COMPOSE) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Jaeger: http://localhost:16686"

up-debug: normalize-source-perms generate-passwords generate-fixtures ## Stack starten inkl. Adminer (Debug-Profil)
	@$(MAKE) --no-print-directory _compose-up-debug

_compose-up-debug:
	$(COMPOSE_DEBUG) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Adminer: http://localhost:8081 | Jaeger: http://localhost:16686"

down: ## Stack stoppen
	$(COMPOSE) down

clean: ## Stack stoppen, Volumes und Passwoerter loeschen
	$(COMPOSE) down -v
	# podman unshare: noetig, weil Artefakte via Bind-Mount mit Container-UIDs
	# (z. B. 100032 fuer www-data) geschrieben werden und vom Host nicht geloescht
	# werden koennen. Im User-Namespace ist der Aufrufer root und darf alles.
	podman unshare rm -rf artifacts/layer1 artifacts/layer2 artifacts/layer3 artifacts/layer4 artifacts/layer5
	mkdir -p artifacts/layer1 artifacts/layer2 artifacts/layer3 artifacts/layer4 artifacts/layer5
	: > artifacts/traces.json && chmod 666 artifacts/traces.json
	@if [ -f .env ]; then \
		for key in MYSQL_ROOT_PASSWORD MYSQL_PASSWORD \
			MYSQL_SECURITY_ROOT_PASSWORD MYSQL_SECURITY_PASSWORD \
			WEBTREES_ADMIN_PASSWORD WEBTREES_TEST_USER_PASSWORD; do \
			sed -i "s/^$${key}=.*/$${key}=/" .env; \
		done; \
		echo "Passwoerter in .env zurueckgesetzt."; \
	fi

setup: up ## webtrees im Container einrichten (DB-Migration, Fixtures, Admin-User)
	@$(MAKE) --no-print-directory _setup-exec

# Sub-Make: .env wird neu gelesen, damit nach generate-passwords die aktuellen Passwoerter gelten.
_setup-exec:
	$(COMPOSE) exec webtrees /usr/local/bin/setup-webtrees.sh

test-all: setup test-static ## Alle Teststufen (Statik=harter Gate; je Stufe frisches make setup, rot bei jedem Fehler)
	@rc=0; \
	for tgt in test-unit test-integration test-e2e test-performance; do \
	  echo ""; \
	  echo "=== make setup && make $$tgt ==="; \
	  if $(MAKE) --no-print-directory setup && $(MAKE) --no-print-directory $$tgt; then :; else \
	    echo "FEHLGESCHLAGEN: $$tgt (oder dessen Setup)"; rc=1; \
	  fi; \
	done; \
	echo ""; \
	python3 scripts/summarize-test-all.py --artifacts-dir artifacts/ || true; \
	if [ "$$rc" -ne 0 ]; then echo "test-all: mindestens ein Layer fehlgeschlagen (Exit 1)."; fi; \
	exit $$rc

test-static: ## Statischer Test (PHPStan + PHPCS + Trivy)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer1-static/run.sh
	@echo ""
	@echo "=== Trivy Security Scan ==="
	@mkdir -p artifacts/layer1
	-podman run --rm \
		-v trivy-cache:/root/.cache/trivy:rw \
		-v $(WEBTREES_SOURCE):/src:ro,z \
		-v ./artifacts/layer1:/output:rw,z \
		$(TRIVY_IMAGE) fs \
		--scanners vuln,misconfig,secret \
		--format json \
		--output /output/trivy-report.json \
		/src
	-podman run --rm \
		-v trivy-cache:/root/.cache/trivy:rw \
		-v $(WEBTREES_SOURCE):/src:ro,z \
		-v ./artifacts/layer1:/output:rw,z \
		$(TRIVY_IMAGE) fs \
		--scanners vuln,misconfig,secret \
		--format table \
		--output /output/trivy-report.txt \
		/src
	@echo "  Trivy: Bericht unter artifacts/layer1/trivy-report.{json,txt}"

test-unit: ## Teststufe 1 — Komponententest (PHPUnit, isoliert)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer2-unit/run.sh

test-integration: ## Teststufe 2 — Komponentenintegrationstest (PHPUnit + MySQL)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer3-integration/run.sh
	mkdir -p artifacts/layer3
	podman cp webtrees:/coverage/layer3-coverage.xml artifacts/layer3/coverage.xml

test-integration-quick: ## Komponentenintegrationstest — 3 repraesentative Faelle
	$(COMPOSE) exec webtrees vendor/bin/phpunit \
	    --configuration=/tests/layer3-integration/phpunit-integration.xml \
	    --filter='SearchIntegrationTest|PrivacyVisibilityTest|TreeOperationsTest' \
	    --log-junit=/artifacts/layer3/phpunit-quick.xml \
	    --coverage-clover=/coverage/layer3-coverage.xml
	mkdir -p artifacts/layer3
	podman cp webtrees:/coverage/layer3-coverage.xml artifacts/layer3/coverage.xml

test-integration-security-%: ## Security-Audit-Einzeltask (z. B. make test-integration-security-042)
	@mkdir -p artifacts/security-trace/SEC-AUDIT-$*
	$(COMPOSE) exec -e WEBTREES_SECURITY_TRACE=1 webtrees vendor/bin/phpunit \
	    --configuration=/tests/layer3-integration/phpunit-integration.xml \
	    --filter='SecAudit$*Test' \
	    --no-coverage

test-e2e-quick: ## Systemtest — 3 repraesentative Faelle mit OTel-Korrelation
	@RUN_ID=$$(uuidgen); \
	echo "Testlauf: $$RUN_ID"; \
	mkdir -p artifacts/layer4; \
	scripts/truncate-perfschema.sh || true; \
	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright /bin/bash /tests/e2e/run.sh \
	    homepage.spec.ts individual.spec.ts search-forms.spec.ts; \
	scripts/extract-perfschema.sh layer4 || true; \
	scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
	    --output-json artifacts/layer4/trace-report.json || true
	    # --output-text artifacts/layer4/trace-report.txt — temporär deaktiviert (2026-04-13)

test-e2e: ## Teststufe 3 — Systemtest (Playwright) mit OTel-Korrelation
	@RUN_ID=$$(uuidgen); \
	echo "Testlauf: $$RUN_ID"; \
	mkdir -p artifacts/layer4; \
	scripts/truncate-perfschema.sh || true; \
	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright /bin/bash /tests/e2e/run.sh; \
	scripts/extract-perfschema.sh layer4 || true; \
	scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
	    --output-json artifacts/layer4/trace-report.json || true
	    # --output-text artifacts/layer4/trace-report.txt — temporär deaktiviert (2026-04-13)

test-performance: ## Performanztest (Playwright-Metrics + Baseline-Vergleich + OTel)
	@RUN_ID=$$(uuidgen); \
	echo "Testlauf: $$RUN_ID"; \
	mkdir -p artifacts/layer5; \
	scripts/truncate-perfschema.sh || true; \
	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright /bin/bash /tests/performance/run.sh; \
	scripts/extract-perfschema.sh layer5 || true; \
	scripts/trace-report.sh --run-id $$RUN_ID --layer 5 \
	    --output-json artifacts/layer5/trace-report.json || true
	    # --output-text artifacts/layer5/trace-report.txt — temporär deaktiviert (2026-04-13)

perfschema-truncate: ## PerfSchema-Daten zuruecksetzen
	scripts/truncate-perfschema.sh

perfschema-extract: ## PerfSchema-Daten extrahieren (LAYER=layer3|layer4|layer5)
	scripts/extract-perfschema.sh $(LAYER)

trace-report: ## Trace-Report generieren (RUN_ID=... LAYER=3|4|5)
	@if [ -z "$(RUN_ID)" ]; then \
	    echo "Fehler: RUN_ID nicht gesetzt. Aufruf: make trace-report RUN_ID=<uuid> [LAYER=3|4|5]"; \
	    exit 1; \
	fi
	scripts/trace-report.sh \
	    --run-id "$(RUN_ID)" \
	    --traces-file artifacts/traces.json \
	    $(if $(LAYER),--layer $(LAYER)) \
	    --output-json artifacts/trace-report-$(RUN_ID).json

security-build: clone-upstream ## Security-Image bauen (Distribution-Build)
	scripts/build-security-image.sh

test-security: security-build generate-passwords ## Sicherheitstest (Distribution + Wizard + Prüfpunkte)
	@$(MAKE) --no-print-directory _security-run

_security-run:
	$(COMPOSE_SECURITY) up -d webtrees-security mysql-security playwright
	scripts/security-filesystem-checks.sh --pre-wizard
	$(COMPOSE_SECURITY) exec playwright npx playwright test \
	    --config=/tests/e2e/playwright-security.config.ts
	scripts/security-filesystem-checks.sh --post-wizard
	-podman stop webtrees-security mysql-security 2>/dev/null
	-podman rm -f webtrees-security mysql-security 2>/dev/null

security-up: security-build generate-passwords ## Security-Stack starten (ohne Tests)
	@$(MAKE) --no-print-directory _security-compose-up

_security-compose-up:
	$(COMPOSE_SECURITY) up -d webtrees-security mysql-security

security-down: ## Security-Stack stoppen + entfernen
	-podman stop webtrees-security mysql-security 2>/dev/null
	-podman rm -f webtrees-security mysql-security 2>/dev/null

security-clean: security-down ## Security-Stack stoppen + Volumes löschen
	-podman volume rm -f webtrees-testing-platform_mysql-security-data 2>/dev/null

logs: ## Container-Logs anzeigen
	$(COMPOSE) logs -f

status: ## Container-Status anzeigen
	$(COMPOSE) ps

crap-report: ## CRAP-Score-Report aus artifacts/layer3/coverage.xml (CRAP > 100, 0% Coverage)
	podman cp artifacts/layer3/coverage.xml webtrees:/tmp/crap-coverage.xml
	$(COMPOSE) exec webtrees php /tests/layer3-integration/scripts/crap-report.php /tmp/crap-coverage.xml
	$(COMPOSE) exec webtrees rm -f /tmp/crap-coverage.xml

mysql-shell: ## MySQL-Shell oeffnen
	$(COMPOSE) exec mysql mysql -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE)

php-shell: ## PHP-Shell im webtrees-Container
	$(COMPOSE) exec webtrees bash

db-dump: ## Testdatenbank dumpen (nach artifacts/)
	$(COMPOSE) exec mysql mysqldump -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE) > artifacts/db-dump.sql
	@echo "Dump: artifacts/db-dump.sql"
