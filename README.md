# Dishrag

This is a simple hoppy/learning project about RAG - Retrival Augmented Generation.

## Plan

See [Plan.md](Plan.md) for the plan.

## Tech Stack

- PHP / Symfony
- Docker
- GCP
- ???
- ???

## Setup

The Orchestrator Symfony app currently lives in `orchestrator/`.

### Start local environment

From the repository root:

```bash
docker compose -f orchestrator/compose.yaml -f orchestrator/compose.override.yaml up --build -d
```

This starts:

- `app` on [http://localhost:8000](http://localhost:8000)
- `database` on `localhost:5432`
- `mailer` (Mailpit UI) on [http://localhost:8025](http://localhost:8025)

### Run tests locally

Containerized (recommended for consistency):

```bash
docker compose -f orchestrator/compose.yaml -f orchestrator/compose.override.yaml exec app vendor/bin/phpunit --configuration phpunit.dist.xml
```

Host-based (if PHP/composer are installed locally):

```bash
cd orchestrator
composer install
vendor/bin/phpunit --configuration phpunit.dist.xml
```

## CI

GitHub Actions runs on `push` and `pull_request`.

Workflow steps:

1. Check out repository
2. Set up PHP 8.4 with `pdo_pgsql`
3. Install Composer dependencies (with cache)
4. Run PHPUnit in `orchestrator/`

Workflow file: `.github/workflows/ci.yml`

## Troubleshooting

- Port conflicts:
  - change or free `8000`, `5432`, `1025`, `8025` before running compose.
- App container starts but vendor is missing:
  - rerun `docker compose ... up --build -d`, then `exec app composer install`.
- Database-related errors:
  - wait until Postgres is healthy, then retry test/command execution.
- Env overrides:
  - use `orchestrator/.env.local` for local-only overrides, do not commit secrets.
