# SPDX-License-Identifier: AGPL-3.0-or-later

# webtrees Test-Stack — Makefile
# Alle Targets verwenden podman-compose

-include .env
export

COMPOSE = podman-compose -f compose.yaml
COMPOSE_DEBUG = podman-compose -f compose.yaml --profile debug
COMPOSE_SECURITY = podman-compose -f compose.yaml --profile security

.PHONY: help clone-upstream generate-passwords up down clean setup test-all test-static test-unit test-integration test-integration-quick test-e2e test-e2e-quick test-performance perfschema-truncate perfschema-extract trace-report security-build test-security security-up security-down security-clean logs status

help: ## Hilfe anzeigen
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

clone-upstream: ## webtrees-Source klonen (falls nicht vorhanden)
	scripts/clone-upstream.sh

generate-passwords: ## Passwoerter in .env generieren (falls leer)
	scripts/generate-passwords.sh

up: clone-upstream generate-passwords ## Stack starten (alle Container)
	$(COMPOSE) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Jaeger: http://localhost:16686"

up-debug: clone-upstream generate-passwords ## Stack starten inkl. Adminer (Debug-Profil)
	$(COMPOSE_DEBUG) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Adminer: http://localhost:8081 | Jaeger: http://localhost:16686"

down: ## Stack stoppen
	$(COMPOSE) down

clean: ## Stack stoppen, Volumes und Passwoerter loeschen
	$(COMPOSE) down -v
	rm -rf artifacts/layer*/*
	@if [ -f .env ]; then \
		for key in MYSQL_ROOT_PASSWORD MYSQL_PASSWORD \
			MYSQL_SECURITY_ROOT_PASSWORD MYSQL_SECURITY_PASSWORD \
			WEBTREES_ADMIN_PASSWORD WEBTREES_TEST_USER_PASSWORD; do \
			sed -i "s/^$${key}=.*/$${key}=/" .env; \
		done; \
		echo "Passwoerter in .env zurueckgesetzt."; \
	fi

setup: up ## webtrees im Container einrichten (DB-Migration, Fixtures, Admin-User)
	$(COMPOSE) exec webtrees /usr/local/bin/setup-webtrees.sh

test-all: setup test-static test-unit test-integration test-e2e test-performance ## Alle Teststufen ausführen

test-static: ## Statischer Test (PHPStan + PHPCS)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer1-static/run.sh

test-unit: ## Teststufe 1 — Komponententest (PHPUnit, isoliert)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer2-unit/run.sh

test-integration: ## Teststufe 2 — Komponentenintegrationstest (PHPUnit + MySQL)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer3-integration/run.sh

test-integration-quick: ## Komponentenintegrationstest — 3 repraesentative Faelle
	$(COMPOSE) exec webtrees vendor/bin/phpunit \
	    --configuration=/tests/layer3-integration/phpunit-integration.xml \
	    --filter='SearchIntegrationTest|PrivacyVisibilityTest|TreeOperationsTest' \
	    --log-junit=/artifacts/layer3/phpunit-quick.xml \
	    --coverage-html=/artifacts/layer3/coverage-html \
	    --coverage-clover=/artifacts/layer3/coverage.xml

test-e2e-quick: ## Systemtest — 3 repraesentative Faelle mit OTel-Korrelation
	@RUN_ID=$$(uuidgen); \
	echo "Testlauf: $$RUN_ID"; \
	scripts/truncate-perfschema.sh || true; \
	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright npx playwright test \
	    --config=/tests/e2e/playwright.config.ts \
	    homepage.spec.ts individual.spec.ts search-forms.spec.ts; \
	scripts/extract-perfschema.sh layer4 || true; \
	scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
	    --output-json artifacts/trace-report-$$RUN_ID.json || true

test-e2e: ## Teststufe 3 — Systemtest (Playwright) mit OTel-Korrelation
	@RUN_ID=$$(uuidgen); \
	echo "Testlauf: $$RUN_ID"; \
	scripts/truncate-perfschema.sh || true; \
	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright npx playwright test \
	    --config=/tests/e2e/playwright.config.ts; \
	scripts/extract-perfschema.sh layer4 || true; \
	scripts/trace-report.sh --run-id $$RUN_ID --layer 4 \
	    --output-json artifacts/trace-report-$$RUN_ID.json || true

test-performance: ## Performanztest (Playwright-Metrics + Baseline-Vergleich + OTel)
	@RUN_ID=$$(uuidgen); \
	echo "Testlauf: $$RUN_ID"; \
	scripts/truncate-perfschema.sh || true; \
	$(COMPOSE) exec -e TEST_RUN_ID=$$RUN_ID playwright npx playwright test \
	    --config=/tests/performance/playwright.config.ts; \
	scripts/extract-perfschema.sh layer5 || true; \
	scripts/trace-report.sh --run-id $$RUN_ID --layer 5 \
	    --output-json artifacts/trace-report-$$RUN_ID.json || true

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
	$(COMPOSE_SECURITY) up -d webtrees-security mysql-security playwright
	scripts/security-filesystem-checks.sh --pre-wizard
	$(COMPOSE_SECURITY) exec playwright npx playwright test \
	    --config=/tests/e2e/playwright-security.config.ts
	scripts/security-filesystem-checks.sh --post-wizard
	-podman stop webtrees-security mysql-security 2>/dev/null
	-podman rm -f webtrees-security mysql-security 2>/dev/null

security-up: security-build generate-passwords ## Security-Stack starten (ohne Tests)
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

mysql-shell: ## MySQL-Shell oeffnen
	$(COMPOSE) exec mysql mysql -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE)

php-shell: ## PHP-Shell im webtrees-Container
	$(COMPOSE) exec webtrees bash

db-dump: ## Testdatenbank dumpen (nach artifacts/)
	$(COMPOSE) exec mysql mysqldump -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE) > artifacts/db-dump.sql
	@echo "Dump: artifacts/db-dump.sql"
