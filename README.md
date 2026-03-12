# Socket Messenger

[![CI](https://github.com/AndriyKhokhlov07/socket-messenger/actions/workflows/ci.yml/badge.svg)](https://github.com/AndriyKhokhlov07/socket-messenger/actions/workflows/ci.yml)

Production-ready real-time messenger built with Laravel 12, Reverb, Redis, and MySQL.  
Project includes authentication, contacts, private chat channels, typing indicators, delivery/read statuses, and a custom messenger UI.

---

## 1. Tech Stack

### Backend
- PHP `^8.2` (Docker image: `php:8.3-fpm`)
- Laravel Framework `^12.0`
- Laravel Reverb `^1.0`
- Laravel Breeze `^2.4`
- Redis (cache, queue, rate limiter, broadcasting support)
- MySQL 8

### Frontend
- Vite `^7.0.7`
- Tailwind CSS `^3.1.0`
- Alpine.js `^3.4.2`
- Axios `^1.13.6`
- Laravel Echo `^2.3.1`
- Pusher JS `^8.4.0` (Reverb client protocol)

### Infrastructure (Docker Compose)
- `app` (PHP-FPM + Composer + PHP Redis extension)
- `nginx` (public HTTP entrypoint on `localhost:8080`)
- `mysql`
- `redis`
- `reverb` (WebSocket server on `localhost:6001`)
- `node` (build/runtime for Vite/NPM commands)

---

## 2. Main Features

- Registration/Login/Logout (Laravel Breeze)
- Presence-based contact list with online/offline states
- Real-time private messaging
- Attachments: images, videos, documents
- Emoji picker in message composer
- Typing events
- Message statuses: `sent`, `delivered`, `read`
- Read receipts in chat + contact preview metadata
- Additional workspace pages: `Dashboard`, `Contacts`, `Shared Media`, `Profile`
- Responsive messenger UI

### CI Quality Gate
- GitHub Actions workflow: `.github/workflows/ci.yml`
- Trigger: every `push` and `pull_request` to `main`
- Checks: backend tests (`php artisan test`) + frontend build (`npm run build`)

---

## 3. Requirements

### Recommended (Docker mode)
- Docker Desktop (or Docker Engine + Compose plugin)
- Git

### Optional (local non-Docker mode)
- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8+
- Redis 7+

---

## 4. Quick Start (Docker, Recommended)

```bash
# 1) Clone and enter project
git clone https://github.com/AndriyKhokhlov07/socket-messenger
cd socket-messenger

# 2) Create env
cp .env.example .env

# 3) Build containers
docker compose build app reverb

# 4) Start services
docker compose up -d

# 5) Install PHP dependencies
docker compose exec app composer install

# 6) Install Node dependencies
docker compose exec node npm install

# 7) Generate app key
docker compose exec app php artisan key:generate

# 8) Run migrations
docker compose exec app php artisan migrate

# 9) Publish storage symlink (required for attachments)
docker compose exec app php artisan storage:link

# 10) Clear stale caches
docker compose exec app php artisan optimize:clear

# 11) Build frontend assets
docker compose exec node npm run build
```

Open:
- App: `http://localhost:8080`
- Chat: `http://localhost:8080/chat`
- Dashboard: `http://localhost:8080/dashboard`
- Contacts: `http://localhost:8080/contacts`
- Shared Media: `http://localhost:8080/media`
- Login: `http://localhost:8080/login`
- Register: `http://localhost:8080/register`
- Reverb WS endpoint: `ws://localhost:6001`

---

## 5. Environment Configuration

Use `.env` based on `.env.example`.  
For Docker setup, keep these values:

```env
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=messenger
DB_USERNAME=root
DB_PASSWORD=root

SESSION_DRIVER=database
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=localkey
REVERB_APP_SECRET=localsecret
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=6001
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 6. Daily Commands (Cheat Sheet)

### Containers

```bash
docker compose up -d
docker compose down
docker compose ps
docker compose logs -f nginx
docker compose logs -f app
docker compose logs -f reverb
```

### Laravel (inside `app`)

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan migrate
docker compose exec app php artisan route:list
docker compose exec app php artisan test
docker compose exec app php artisan tinker
```

### Queue worker (run when you need explicit queue processing)

```bash
docker compose exec app php artisan queue:work --tries=1 --timeout=0
```

### Frontend (inside `node`)

```bash
docker compose exec node npm install
docker compose exec node npm run dev
docker compose exec node npm run build
```

---

## 7. HTTP Routes (Core)

- `GET /chat` - messenger UI
- `GET /chat/contacts` - contacts list payload
- `GET /contacts` - contacts directory page
- `GET /media` - shared media page
- `GET /messages/{user}` - message history
- `POST /messages` - send message
- `PATCH /messages/{message}/status` - update message status
- `POST /messages/typing` - typing event

Auth/Profile:
- `GET /login`, `GET /register`, `GET /dashboard`
- `GET/PATCH/DELETE /profile`

---

## 8. Package Inventory

### Composer `require`
- `laravel/framework:^12.0`
- `laravel/reverb:^1.0`
- `laravel/tinker:^2.10.1`

### Composer `require-dev`
- `laravel/breeze:^2.4`
- `laravel/pail:^1.2.2`
- `laravel/pint:^1.24`
- `laravel/sail:^1.41`
- `phpunit/phpunit:^11.5.50`
- `fakerphp/faker:^1.23`
- `mockery/mockery:^1.6`
- `nunomaduro/collision:^8.6`

### NPM `dependencies`
- `laravel-echo:^2.3.1`
- `pusher-js:^8.4.0`

### NPM `devDependencies`
- `vite:^7.0.7`
- `tailwindcss:^3.1.0`
- `@tailwindcss/forms:^0.5.2`
- `@tailwindcss/postcss:^4.2.1`
- `@tailwindcss/vite:^4.0.0`
- `laravel-vite-plugin:^2.0.0`
- `alpinejs:^3.4.2`
- `axios:^1.13.6`
- `autoprefixer:^10.4.2`
- `postcss:^8.4.31`
- `concurrently:^9.0.1`

---

## 9. Troubleshooting

### A) `Class "Redis" not found`

Cause: PHP Redis extension is missing in container image.  
Fix:

```bash
docker compose build app reverb
docker compose up -d app reverb
docker compose exec app php -m | grep -i redis
```

### B) `502 Bad Gateway` after recreating `app`

Cause: nginx points to stale upstream container IP.  
Fix:

```bash
docker compose restart nginx
```

Note: project nginx config already uses Docker DNS resolver to reduce this issue.

### C) `'vite' is not recognized`

Run Vite via `node` container:

```bash
docker compose exec node npm run build
```

### D) Assets not updating in browser

```bash
docker compose exec node npm run build
```

Then hard refresh browser (`Ctrl + F5`).

---

## 10. Optional: Local Run (Without Docker)

Use this only if you already have local PHP/MySQL/Redis/Node configured.

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
php artisan reverb:start
php artisan queue:work --tries=1 --timeout=0
```

---

## 11. GitHub Publish Checklist

Before push:

```bash
# Validate project
docker compose exec app php artisan test
docker compose exec node npm run build
```

If repository is not initialized yet:

```bash
git init
git add .
git commit -m "Initial socket messenger setup"
git branch -M main
git remote add origin https://github.com/AndriyKhokhlov07/socket-messenger.git
git push -u origin main
```

---

## 12. Notes

- Default auth pages and dashboard are styled to match messenger visual language.
- Reverb server runs in dedicated container (`reverb` service).
- For production deployment, configure secure secrets, HTTPS, proper queue supervision, and monitoring.

---

## 13. Collaboration Standards

- [Contributing Guide](./CONTRIBUTING.md)
- [Code of Conduct](./CODE_OF_CONDUCT.md)
- [Bug Report Template](./.github/ISSUE_TEMPLATE/bug_report.yml)
- [Feature Request Template](./.github/ISSUE_TEMPLATE/feature_request.yml)
- [Pull Request Template](./.github/pull_request_template.md)

---

## 14. Release Process

This repository includes an automated release workflow:

- Workflow file: `.github/workflows/release.yml`
- Trigger: pushing a tag that starts with `v` (example: `v1.0.0`)
- Result: GitHub Release is created automatically with generated release notes

Example:

```bash
git tag v1.0.0
git push origin v1.0.0
```
