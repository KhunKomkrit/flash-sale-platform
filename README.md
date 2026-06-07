# Flash Sale Platform

Local Laravel 10 development environment using PHP 8.2-FPM, Nginx,
MySQL 8, and Redis 7.

## Requirements

- Docker Desktop, or Docker Engine with Docker Compose
- `make`

macOS normally includes `make` after installing Xcode Command Line Tools:

```sh
xcode-select --install
```

Ubuntu/Debian users can install it with:

```sh
sudo apt-get update
sudo apt-get install make
```

`make` is only a command shortcut. If it is unavailable, run the equivalent
`docker compose` commands shown in the `Makefile`.

## First-time setup

For a new checkout, build and start the containers, create all database
tables, and insert the initial data:

```sh
make init
```

Open <http://localhost:8000>.

> `make init` runs `php artisan migrate:fresh --seed`. It deletes all existing
> database tables and data, so use it only for initial setup or when a complete
> local database reset is intended.

On the first start, the app container bootstraps Laravel 10 into the
repository when `artisan` is absent. Existing repository and Docker files
are preserved. Later starts skip bootstrapping; if only `vendor/` is absent,
the container runs `composer install`.

Laravel 10 is an intentionally pinned legacy major. The bootstrap command
disables Composer's advisory blocking for `create-project`; review
`composer audit` output and application dependencies before production use.

The entrypoint creates `.env` and generates `APP_KEY` when needed. Database
migrations are intentionally not run automatically.

## Daily use

Stop the containers without removing MySQL or Redis data:

```sh
make stop
```

Start stopped containers without resetting or seeding the database:

```sh
make start
```

Restart running containers without resetting the database:

```sh
make restart
```

The named Docker volumes `mysql_data` and `redis_data` preserve data between
these commands. Avoid `docker compose down -v` unless the stored data should
be deleted.

## Services

| Service | Host from containers | Port |
| --- | --- | --- |
| Nginx | `nginx` | `80` (published as `8000`) |
| MySQL | `mysql` | `3306` |
| Redis | `redis` | `6379` |

Development defaults can be overridden in the shell or a Compose `.env` file:

```dotenv
APP_PORT=8000
MYSQL_DATABASE=flash_sale
MYSQL_USER=flash_sale
MYSQL_PASSWORD=secret
MYSQL_ROOT_PASSWORD=root
```

## Common commands

```sh
# List all Make targets
make help

# Show service status
make status

# Follow service logs
make logs

# Run migrations explicitly
docker compose exec app php artisan migrate

# Run the Laravel test suite
make test

# Run Composer or Artisan commands
docker compose exec app composer install
docker compose exec app php artisan about

# Stop services while preserving data
make stop

# Stop services and remove database/Redis data
docker compose down -v
```

## Generate a CRUD module

Use `make:module` to generate a CRUD API module:

```sh
docker compose exec app php artisan make:module Product \
  --fields="name:string|required,price:decimal|required"
```

The `--fields` option accepts comma-separated definitions in
`name:type|rule` format. Validation rules after the type are optional.
Supported types are `array`, `boolean`, `date`, `datetime`, `decimal`,
`email`, `integer`, `json`, `numeric`, `string`, `text`, `url`, and `uuid`.

You can also generate a module without fields and add the fillable fields
and validation rules later:

```sh
docker compose exec app php artisan make:module Product
```

For the `Product` example, the command generates this structure:

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ProductController.php
│   ├── Requests/
│   │   ├── StoreProductRequest.php
│   │   └── UpdateProductRequest.php
│   └── Resources/
│       └── ProductResource.php
├── Models/
│   └── Product.php
├── Repositories/
│   └── ProductRepository.php
└── Services/
    └── ProductService.php
routes/
└── api/
    └── products.php
```

The generated route uses `Route::apiResource` and is loaded under the
`/api` prefix with `auth:sanctum` middleware. The command does not create a
migration or factory.
