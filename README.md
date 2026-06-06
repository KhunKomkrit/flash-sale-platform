# Flash Sale Platform

Local Laravel 10 development environment using PHP 8.2-FPM, Nginx,
MySQL 8, and Redis 7.

## Start the stack

```sh
docker compose up --build
```

Open <http://localhost:8000>.

On the first start, the app container bootstraps Laravel 10 into the
repository when `artisan` is absent. Existing repository and Docker files
are preserved. Later starts skip bootstrapping; if only `vendor/` is absent,
the container runs `composer install`.

Laravel 10 is an intentionally pinned legacy major. The bootstrap command
disables Composer's advisory blocking for `create-project`; review
`composer audit` output and application dependencies before production use.

The entrypoint creates `.env` and generates `APP_KEY` when needed. Database
migrations are intentionally not run automatically.

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
# Run migrations explicitly
docker compose exec app php artisan migrate

# Run the Laravel test suite
docker compose exec app php artisan test

# Run Composer or Artisan commands
docker compose exec app composer install
docker compose exec app php artisan about

# Stop services
docker compose down

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
