# Flash Sale Platform

Laravel 10 development environment using PHP 8.2-FPM, Nginx, MySQL 8,
Redis 7, and Docker Compose.

## Implemented Assessment Work

| Assessment task | Implemented scope |
| --- | --- |
| Task 1 - Setup and Architecture | Docker environment, setup commands, Sanctum route protection, layered architecture, database seeding, and custom module generators |
| Task 2 - Database and Indexing | Database schema, constraints, foreign keys, soft deletes, and initial indexes |
| Task 5 - Code Review | Corrected product stock reduction, top-selling query, and active-product search |
| Supporting tests | Generator tests currently pass; this does not represent completion of Task 6 domain testing |

## Task 1 - Setup and Architecture

### Requirements

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

### First-Time Setup

Build and start the containers, create the database tables, and seed the
initial data:

```sh
make init
```

Open <http://localhost:8000>.

> `make init` runs `php artisan migrate:fresh --seed`. It deletes all existing
> database tables and data.

On the first start, the app container bootstraps Laravel 10 into the repository
when `artisan` is absent. Existing repository and Docker files are preserved.
Later starts skip bootstrapping; if only `vendor/` is absent, the container
runs `composer install`.

The entrypoint creates `.env` and generates `APP_KEY` when needed. Database
migrations are intentionally not run automatically.

### Daily Use

```sh
# Start stopped containers without resetting data
make start

# Stop containers while preserving MySQL and Redis data
make stop

# Restart running containers
make restart

# Show service status
make status

# Follow service logs
make logs
```

The named Docker volumes `mysql_data` and `redis_data` preserve data between
these commands. Use `docker compose down -v` only when the stored data should
be deleted.

### Services

| Service | Container host | Port |
| --- | --- | --- |
| Nginx | `nginx` | `80` (published as `8000`) |
| MySQL | `mysql` | `3306` (published as `3306`) |
| Redis | `redis` | `6379` |

Development defaults can be overridden in the shell or a Compose `.env` file:

```dotenv
APP_PORT=8000
MYSQL_DATABASE=flash_sale
MYSQL_USER=flash_sale
MYSQL_PASSWORD=secret
MYSQL_ROOT_PASSWORD=root
```

### Architecture

Generated API modules use the following request flow:

```text
HTTP request
  -> routes/api.php (api prefix and auth:sanctum)
  -> routes/api/<resource>.php
  -> Form Request
  -> Controller
  -> Service
  -> Repository
  -> Eloquent model / MySQL
  -> API Resource
```

| Layer | Responsibility |
| --- | --- |
| Route | URI, HTTP verb, middleware, and controller mapping |
| Form Request | Input validation and authorization hook |
| Controller | HTTP orchestration and response status |
| Service | Application rules and use-case coordination |
| Repository | Persistence queries and atomic database updates |
| Model | Eloquent mapping and relationships |
| API Resource | Response transformation |

Laravel Sanctum is installed. Routes loaded through `routes/api.php`, including
generated module routes, are protected by `auth:sanctum`. The `User` model
uses `HasApiTokens`.

### Database Seeding

`DatabaseSeeder` disables the query log and uses chunked inserts to control
memory usage. A fresh seed creates:

| Record type | Count |
| --- | ---: |
| Users | 1,001 |
| Products | 500 |
| Sale events | 20 |
| Orders | 100,000 |
| Order logs | 100,000 |

Orders are inserted in chunks of 250. Order logs are generated using
`chunkById`.

### Module Generators

Generate a CRUD API module:

```sh
docker compose exec app php artisan make:module Product \
  --fields="name:string|required,price:decimal|required"
```

The `--fields` option accepts comma-separated definitions in
`name:type|rule` format. Supported types are `array`, `boolean`, `date`,
`datetime`, `decimal`, `email`, `integer`, `json`, `numeric`, `string`,
`text`, `url`, and `uuid`.

A module can also be generated without fields:

```sh
docker compose exec app php artisan make:module Product
```

The generated structure is:

```text
app/
|-- Http/
|   |-- Controllers/ProductController.php
|   |-- Requests/StoreProductRequest.php
|   |-- Requests/UpdateProductRequest.php
|   `-- Resources/ProductResource.php
|-- Models/Product.php
|-- Repositories/ProductRepository.php
`-- Services/ProductService.php
routes/
`-- api/products.php
```

The generated route uses `Route::apiResource`, is loaded under `/api`, and is
protected by `auth:sanctum`. Existing files are not overwritten.

Individual layers can also be generated:

```sh
docker compose exec app php artisan make:repository Product
docker compose exec app php artisan make:service Product
docker compose exec app php artisan make:route-api Product
```

The commands accept `ProductRepository`, `ProductService`, and
`ProductController` without duplicating the suffix.

### Common Commands

```sh
# List Make targets
make help

# Run migrations without reseeding
docker compose exec app php artisan migrate

# Run the test suite
make test

# Run Composer or Artisan commands
docker compose exec app composer install
docker compose exec app php artisan about

# Remove containers and local database/Redis data
docker compose down -v
```

## Task 2 - Database and Indexing (Implemented Portion)

The project includes migrations and Eloquent models for:

- users
- products
- sale events
- orders
- order logs
- Sanctum personal access tokens
- failed jobs

The domain schema includes foreign keys, soft deletes for products and orders,
a unique SKU constraint, a unique user/product/sale-event order constraint,
and composite indexes for product listings, active events, order history,
event dashboards, product sales, and order-log lookups.

The assessment answers and measured `EXPLAIN ANALYZE` evidence are not included
here because that work is not complete.

## Task 5 - Code Review (Implemented Corrections)

The existing product service and repository provide:

- positive-quantity validation before stock reduction
- atomic stock decrement guarded by `stock >= quantity`
- paid-order aggregation for top-selling products
- active-product listing
- trimmed active-product name search
- bounded results through method limit parameters

## Supporting Test Evidence

Run:

```sh
make test
```

Verified on June 7, 2026:

```text
Tests: 30 passed (121 assertions)
```

The current test suite covers the custom module, repository, service, and API
route generators. It does not complete Task 6 domain testing.
