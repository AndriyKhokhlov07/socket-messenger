# Contributing Guide

Thanks for contributing to `socket-messenger`.

## Development Setup

1. Fork repository and clone your fork:
```bash
git clone https://github.com/<your-username>/socket-messenger.git
cd socket-messenger
```
2. Create environment file:
```bash
cp .env.example .env
```
3. Start stack:
```bash
docker compose build app reverb
docker compose up -d
docker compose exec app composer install
docker compose exec node npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

## Branching

- Branch from `main`
- Use descriptive branch names:
  - `feature/<short-description>`
  - `fix/<short-description>`
  - `docs/<short-description>`

## Commit Messages

Use clear commit messages, preferably Conventional Commits:

- `feat: add typing indicator timeout`
- `fix: correct redis cache connection`
- `docs: update setup instructions`
- `ci: add release workflow`

## Quality Checks (Required Before PR)

```bash
docker compose exec app php artisan test
docker compose exec node npm run build
```

## Pull Request Rules

- Keep PR focused on one concern.
- Include problem statement and solution.
- Add screenshots/GIF for UI changes.
- Ensure CI is green.
- Do not commit secrets or local env files.

## Security

If you discover a security issue, do not open a public issue with exploit details.  
Contact repository owner privately first.

