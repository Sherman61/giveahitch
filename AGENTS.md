# Repository Guidelines

## Project Structure & Module Organization
- `public/` holds browser-facing PHP pages and static assets such as `forgot-password.php`, `reset-code.php`, and Bootstrap-based layouts. Serve this directory when running PHP’s built-in server.
- `api/` contains JSON endpoints (e.g., `forgot_password_request.php`) that rely on shared helpers from `lib/`. All files begin with `declare(strict_types=1);` and expect `Content-Type: application/json`.
- `lib/` exposes reusable modules: authentication, session handling, mailer, notifications, and WebSocket helpers. Namespacing follows `App\...`.
- `config/db.php` defines the `db()` factory. Database credentials are usually injected through env vars (`DB_DSN`, `DB_USER`, `DB_PASS`).
- Front-end JavaScript lives in `assets/js/`, while Composer and npm dependencies resolve to `vendor/` and `node_modules/` respectively.

## Build, Test, and Development Commands
- `composer install` — installs PHP dependencies (Dotenv, Symfony HttpClient, etc.). Run after cloning or updating `composer.lock`.
- `npm install` — installs the small Node/Express toolchain used for WebSocket helpers under `ws/`.
- `php -S localhost:8000 -t public` — quick local server; ensure `.env` is present or environment variables are exported.
- `node ws/server.mjs` — runs the Socket.IO server; configure origins via `WS_ORIGINS` or `ORIGIN`.

## Coding Style & Naming Conventions
- Stick to strict typing and PSR-12 style: `<?php declare(strict_types=1);` on line 1, 2 spaces for indentation in mixed PHP/HTML blocks, and snake_case for array keys mirrored from DB columns.
- Always access the database through `db()`; store the resulting PDO in a local `$pdo`.
- JSON endpoints return via helper functions like `json_out` and must `header('Content-Type: application/json; charset=utf-8');`.
- Front-end JS uses modern syntax with `const/let`, fetch, and async/await; keep modules self-invoking (`(() => { ... })();`).

## Testing Guidelines
- No automated suite is bundled; rely on targeted manual tests. Validate flows end-to-end (forgot password, login, ride management) using the PHP server plus API responses (inspect Network tab for 200s/JSON payloads).
- When adding PHP modules, prefer lightweight assertions: mock requests with `curl` or `httpie` pointing at localhost and inspect logs in `storage/logs` or PHP error output.

## Commit & Pull Request Guidelines
- Follow existing history: short, imperative commit subjects (`Fix forgot password flow`, `Handle reset email failures`). Group related changes per commit.
- PRs should describe context, testing performed, and any config changes (.env keys, schema updates). Include screenshots or cURL output when altering UI/API responses.
- Reference issue numbers or Trello cards where possible, and call out any new environment variables (`APP_KEY`, `MAILTRAP_TOKEN`) in the PR body.

## Security & Configuration Tips
- Keep `.env` (or environment variables) synced across PHP and Node servers—`APP_KEY` secures password reset tokens, while `MAILTRAP_TOKEN` handles outbound email.
- Never commit credentials; rely on `.env.example` if sharing defaults.
- Check database migrations before deploying—`password_resets` is auto-created by `forgot_password_request.php`, but schema dumps live in `/var/www/glitchahitch/*.sql` if manual changes are needed.
