# School Management — PHP 8.2 compatible delivery

This archive includes the current Laravel backend and Next.js frontend together.

- school-backend/: API, PHP ^8.2; dependencies locked for PHP 8.2.12.
- school-frontend/: current frontend source and npm lock file.
- school-management-php82.bundle: both projects and commit history (Laravel at root, frontend/ inside the repository).

## Backend

Open a terminal in school-backend/:

```sh
composer install
composer check-platform-reqs
cp .env.example .env
php artisan key:generate
```

On Windows use `copy .env.example .env` instead of `cp`.
Configure database credentials in .env, then run:

```sh
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

See school-backend/README.md for first-admin credentials, optional demo setup and production deployment. Do not run migrate:fresh on an existing database. You do not need composer update or --ignore-platform-reqs to install this release.

## Frontend

Open another terminal in school-frontend/:

```sh
npm ci
cp .env.example .env.local
npm run dev
```

On Windows use `copy .env.example .env.local`. Configure API_URL=http://127.0.0.1:8000/api/v1 and the frontend URL in .env.local.

## Validation and scope

23 backend tests / 684 assertions passed on PHP 8.2.32, along with Composer installation/platform checks, frontend type checking, production build and 9 HTTP acceptance groups. The exact 8.2.12 runtime was not available locally; the lock resolution targets 8.2.12 and CI is configured for 8.2.12 and 8.3. Browser and MySQL runtime checks remain unverified. Detailed reports and remaining module work are in each project's docs/ folder. This delivery fixes PHP compatibility and includes the existing frontend; the newly requested comprehensive frontend expansion is not represented as complete.

## Commit and GitHub

Local commit: 54dd2f2f86d261fa46dc8c3fb94310a2373a9bf8
The connected GitHub integration previously rejected write access (HTTP 403); this commit is included in the bundle, not pushed.

To restore the repository in a separate directory:

```sh
git clone school-management-php82.bundle school-repository
cd school-repository
git remote set-url origin https://github.com/Shahindpi/school-backend.git
git push -u origin main
```

A GitHub account with write access is required for that push.
