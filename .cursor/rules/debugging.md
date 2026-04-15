# Debugging rule (repo-wide)

When the user reports a bug, test failure, unexpected behavior, build failure, or runtime error:

- Do **not** propose or apply fixes until you have **runtime evidence** of the root cause.
- Use the **4 phases**: Root cause → Pattern analysis → Hypothesis test → Implementation & verification.
- Prefer **minimal instrumentation** (structured logs; no secrets/PII). Remove it after verification or user request.
- Debug in the **same environment** where it fails (Docker/CI vs host).

Symfony + Docker specifics:

- If you see errors like “Attempted to load class `…Bundle`…”, first verify inside the running container:
  - `vendor/autoload.php` exists
  - the package directory exists (e.g. `vendor/symfony/ux-react/...`)
  - `class_exists()` after requiring `vendor/autoload.php`
- If `vendor/autoload.php` exists but required packages are missing, suspect a **stale/partial named `vendor/` volume**.
  - For local dev, a clean reset is often `docker compose down -v` then `up --build -d`.

