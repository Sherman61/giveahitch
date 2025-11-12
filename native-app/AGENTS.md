# Repository Guidelines

## Project Structure & Module Organization
- `public/` serves browser-facing PHP views (mixed PHP/HTML) such as `forgot-password.php`, `reset-code.php`, plus Bootstrap assets. Run `php -S localhost:8000 -t public` to serve it.
- `api/` exposes JSON endpoints (e.g., `forgot_password_request.php`) that share helpers from `lib/`. Every file starts with `<?php declare(strict_types=1);`.
- `lib/` contains namespaced modules (`App\...`) for auth, sessions, mailer, notifications, and WebSockets. `config/db.php` exports the `db()` PDO factory fed by `DB_DSN`, `DB_USER`, `DB_PASS`.
- Front-end/Expo code lives in the repo root (`App.tsx`, `src/`), assets under `assets/`, and native Android artifacts under `android/` (generated via `npx expo prebuild`).

## Build, Test, and Development Commands
- `composer install` / `npm install`: install PHP and Node dependencies after cloning or updating lockfiles.
- `php -S localhost:8000 -t public`: run the PHP stack; ensure `.env` or env vars are set.
- `npx expo start --dev-client`: start Metro for the native app; use `expo run:android` to install the development client.
- `npm run lint` / `npm run test`: run ESLint and Jest for the React Native portion (no automated PHP suite).

## Coding Style & Naming Conventions
- PHP: strict typing, PSR-12 formatting, 2-space indentation in mixed PHP/HTML, snake_case for array keys mirroring DB columns. Always grab a PDO via `db()`.
- JavaScript/TypeScript: modern syntax (`const`/`let`, async/await), modules wrapped in IIFEs for front-end JS (`(() => { ... })();`). Align Expo config through `app.config.js`.
- JSON endpoints must emit `header('Content-Type: application/json; charset=utf-8');` and respond through helpers like `json_out`.

## Testing Guidelines
- No unified suite; run targeted manual tests per flow (login, forgot password, ride management) using the PHP server plus API calls (`curl https://localhost:8000/api/...`). Check `storage/logs` for errors.
- For the Expo app, validate via the dev client on emulator/device; use Metro logs and `expo-doctor` when adjusting native modules.

## Commit & Pull Request Guidelines
- Follow existing history: short, imperative subjects (`Fix forgot password flow`). Group related changes per commit.
- PRs must describe context, manual testing, and any config/env updates; include screenshots or cURL output for UI/API changes and call out new env vars (`APP_KEY`, `MAILTRAP_TOKEN`, etc.).
