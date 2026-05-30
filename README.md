# SODARS Codebase

Laravel 12 foundation for the SODARS ERP, marketplace, provider portal, agent CRM, booking engine, and finance platform.

## Local Setup

The application is configured for the local XAMPP database credentials provided for Sprint 1:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=sodars_db
DB_USERNAME=root
DB_PASSWORD=
```

## Implemented in Sprint 1

- Laravel 12 app scaffold in the project root.
- Documentation moved to `docs/`.
- MySQL database `sodars_db` created locally.
- Sanctum API token authentication installed.
- Spatie Laravel Permission installed.
- SODARS schema migrations added for identity, locations, branches, providers, inventory, campaigns, bookings, finance, CRM, marketplace, notifications, settings, and analytics.
- Seeders added for roles, permissions, sample Hyderabad location hierarchy, tax/platform settings, and a default Super Admin.
- API routes added for health, login, logout, profile, password change, and location lookup chains.
- Portal shell routes added for `/`, `/admin`, `/business`, and `/agents`.

## Default Admin

```txt
Email: admin@sodars.local
Password: password
```

## Useful Commands

```bash
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
php artisan route:list
php artisan test
```

## Key URLs

- Website shell: `http://127.0.0.1:8000/`
- Admin shell: `http://127.0.0.1:8000/admin`
- Business shell: `http://127.0.0.1:8000/business`
- Agents shell: `http://127.0.0.1:8000/agents`
- API health: `http://127.0.0.1:8000/api/health`
- Countries API: `http://127.0.0.1:8000/api/locations/countries`
