# Agent Instructions

## Stack & Layout
- Symfony app is in `orchestrator/`
- Local dev uses Docker Compose from repo root (see `README.md`)

## Debugging (mandatory)
- Follow the 4-phase workflow in `.cursor/rules/debugging.md`
- When a bug occurs in Docker/CI, debug in Docker/CI (avoid host-only “fixes”)
- For Symfony bundle/autoload errors in Docker, validate `vendor/` inside the running container and suspect the named `vendor` volume early

## Commands
- Start dev stack: `docker compose -f orchestrator/compose.yaml -f orchestrator/compose.override.yaml up --build -d`
- PHPUnit (container): `docker compose -f orchestrator/compose.yaml -f orchestrator/compose.override.yaml exec app vendor/bin/phpunit --configuration phpunit.dist.xml`
- PHPUnit (host): `cd orchestrator && vendor/bin/phpunit --configuration phpunit.dist.xml`

## Commit Attribution
- If you create commits, include a `Co-Authored-By:` trailer for the agent.