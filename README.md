# Daymark

A personal task manager built with Vue 3, Laravel 13, and MySQL 8.4.

- Register and sign in with a unique username and password.
- Private projects with custom colors; deleting a project keeps its tasks.
- Create, edit, complete, reopen, and delete tasks with notes, priorities, and due dates.
- Overview counts, completion progress, today/overdue, upcoming, and completed views.
- Search, priority filtering, and sorting by creation, priority, or due date.
- Responsive desktop/mobile interface with empty states and validation feedback.

## Run locally

Requires PHP 8.3+, Composer 2, Node 22.12+ (or 24+), and Docker Compose for the included MySQL service. PHP needs PDO MySQL, mbstring, XML, ctype, curl, fileinfo, and the usual Laravel extensions. Automated tests also need PDO SQLite.

```bash
cd vue-php-medium
cp .env.example .env
composer install
npm ci
php artisan key:generate
docker compose up -d --wait
php artisan migrate
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Open http://localhost:8000 and choose **Create an account**. There are no seeded accounts or shared passwords. Passwords require at least eight characters, a letter, and a number.

For frontend development, run `npm run dev` in another terminal while `php artisan serve` is running. Open the Laravel URL, not the Vite asset-server URL.

The included Compose file runs MySQL on `127.0.0.1:3307`, using the local credentials in `.env.example`. Data persists in the `daymark_mysql` Docker volume. Stop MySQL with `docker compose down` (without `-v` to preserve data). If port 3307 is occupied, set both `FORWARD_DB_PORT` and `DB_PORT` in `.env` to an available port.

To use your own MySQL server, skip Docker and configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` before migrating.

## Verify

```bash
php artisan test
npm run build
vendor/bin/pint --test
```

Feature tests use an isolated in-memory SQLite database. They cover registration, password hashing, login/logout, input validation, task lifecycle, project deletion, unauthenticated access, and cross-user isolation. The same suite can use a **dedicated disposable MySQL database** by overriding `DB_CONNECTION=mysql`, `DB_DATABASE`, and connection variables in the command environment. `RefreshDatabase` resets the selected test database: never point tests at a database with data you want to keep.

## Structure

- `resources/js/App.vue`: Vue workspace, authentication, forms, and filters.
- `resources/css/app.css`: responsive styles.
- `routes/web.php`: same-origin JSON endpoints and the Vue shell.
- `app/Http/Controllers`: authentication and owner-scoped task/project operations.
- `app/Models` and `database/migrations`: users, projects, and tasks.
- `tests/Feature/WorkspaceTest.php`: behavior and authorization tests.

Authentication uses Laravel sessions, hashed passwords, session rotation, login/registration throttling, and CSRF-protected requests. Each task/project mutation verifies ownership; task assignments are restricted to the signed-in user's projects. Search and sorting operate client-side on the personal workspace, suitable for a medium-sized personal task collection.

For production, serve Laravel's `public/` directory using PHP-FPM and a web server, build assets, configure your own database credentials, use HTTPS, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` and `SESSION_SECURE_COOKIE=true`, and run migrations during deployment. The provided Docker credentials and Artisan server are for local development. Fonts are loaded from Google Fonts with local fallbacks.
