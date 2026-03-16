.PHONY: install test unit integration coverage cs cs-fix stan benchmark ci clean

# ── Setup ──────────────────────────────────────────────────────────────────────
install:
	composer install

# ── Tests ──────────────────────────────────────────────────────────────────────
test:
	XDEBUG_MODE=coverage vendor/bin/phpunit

unit:
	vendor/bin/phpunit --testsuite Unit

integration:
	vendor/bin/phpunit --testsuite Integration

coverage:
	XDEBUG_MODE=coverage vendor/bin/phpunit \
		--coverage-html coverage/html \
		--coverage-clover coverage/clover.xml
	@echo "Coverage report: coverage/html/index.html"

# ── Code quality ──────────────────────────────────────────────────────────────
cs:
	vendor/bin/php-cs-fixer check --diff --ansi

cs-fix:
	vendor/bin/php-cs-fixer fix --ansi

stan:
	vendor/bin/phpstan analyse --ansi --memory-limit=256M

# ── Benchmark ─────────────────────────────────────────────────────────────────
benchmark:
	php demo/benchmark.php

# ── CI ────────────────────────────────────────────────────────────────────────
ci: cs stan test run-demo
	@echo ""
	@echo "✅  All CI checks passed."

# ── Cleanup ───────────────────────────────────────────────────────────────────
clean:
	rm -rf vendor coverage .php-cs-fixer.cache .phpunit.result.cache .phpunit.cache

run-demo:
	@for file in demo/*.php; do \
		echo "Running $$file"; \
		php "$$file"; \
		echo ""; \
	done
