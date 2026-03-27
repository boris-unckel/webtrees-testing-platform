# webtrees Test-Stack — Makefile
# Alle Targets verwenden podman-compose

COMPOSE = podman-compose -f compose.yaml
COMPOSE_DEBUG = podman-compose -f compose.yaml --profile debug

.PHONY: help up down clean setup test-all test-static test-unit test-integration test-e2e test-performance logs status

help: ## Hilfe anzeigen
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Stack starten (alle Container)
	$(COMPOSE) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Jaeger: http://localhost:16686"

up-debug: ## Stack starten inkl. Adminer (Debug-Profil)
	$(COMPOSE_DEBUG) up -d --build
	@echo "Stack gestartet. webtrees: http://localhost:8080 | Adminer: http://localhost:8081 | Jaeger: http://localhost:16686"

down: ## Stack stoppen
	$(COMPOSE) down

clean: ## Stack stoppen und Volumes löschen
	$(COMPOSE) down -v
	rm -rf artifacts/layer*/*

setup: up ## webtrees im Container einrichten (DB-Migration, Fixtures, Admin-User)
	$(COMPOSE) exec webtrees /usr/local/bin/setup-webtrees.sh

test-all: setup test-static test-unit test-integration test-e2e test-performance ## Alle Teststufen ausführen

test-static: ## Statischer Test (PHPStan + PHPCS)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer1-static/run.sh

test-unit: ## Teststufe 1 — Komponententest (PHPUnit, isoliert)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer2-unit/run.sh

test-integration: ## Teststufe 2 — Komponentenintegrationstest (PHPUnit + MySQL)
	$(COMPOSE) exec webtrees /bin/bash /tests/layer3-integration/run.sh

test-e2e: ## Teststufe 3 — Systemtest (Playwright)
	$(COMPOSE) exec playwright npx playwright test --config=/tests/e2e/playwright.config.ts

test-performance: ## Performanztest (Playwright-Metrics + Baseline-Vergleich)
	$(COMPOSE) exec playwright npx playwright test --config=/tests/performance/playwright.config.ts

logs: ## Container-Logs anzeigen
	$(COMPOSE) logs -f

status: ## Container-Status anzeigen
	$(COMPOSE) ps

mysql-shell: ## MySQL-Shell öffnen
	$(COMPOSE) exec mysql mysql -u webtrees -pwebtrees_test webtrees_test

php-shell: ## PHP-Shell im webtrees-Container
	$(COMPOSE) exec webtrees bash

db-dump: ## Testdatenbank dumpen (nach artifacts/)
	$(COMPOSE) exec mysql mysqldump -u webtrees -pwebtrees_test webtrees_test > artifacts/db-dump.sql
	@echo "Dump: artifacts/db-dump.sql"
