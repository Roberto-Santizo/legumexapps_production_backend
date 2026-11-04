<p align="center">
  <a href="https://laravel.com" target="_blank"><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/laravel/laravel-original.svg" alt="Laravel" height="100"></a>
  &nbsp;&nbsp;&nbsp;&nbsp;
  <a href="https://www.postgresql.org" target="_blank"><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/postgresql/postgresql-original.svg" alt="PostgreSQL" height="100"></a>
</p>

# Legumex Production Backend

API REST (Laravel 13, PHP 8.5, PostgreSQL 17) para planificación de producción: líneas, SKUs, materiales, planes semanales y tareas.

## Base de datos

Requiere Docker. `.env.example` trae SQLite por defecto, así que primero configura PostgreSQL en `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=legumexapps_production
DB_USERNAME=postgres
DB_PASSWORD=secret
```

`docker-compose.yml` toma estas mismas variables para crear la base de datos.

### Levantar la DB

```bash
docker compose up -d
```

### Migraciones

```bash
php artisan migrate
```

### Seeders

```bash
php artisan db:seed
```

Crea el usuario `admin` (contraseña `password`), los campos de captura y los catálogos.

### Reiniciar la DB

Borra todas las tablas, migra y ejecuta los seeders:

```bash
php artisan migrate:fresh --seed
```
