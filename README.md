# Flash Sale Platform

Laravel 10 development environment using PHP 8.2-FPM, Nginx, MySQL 8,
Redis 7, and Docker Compose.

## Implemented Assessment Work

| Assessment task | Implemented scope |
| --- | --- |
| Task 1 - Setup and Architecture | Docker environment, setup commands, Sanctum route protection, layered architecture, database seeding, and custom module generators |
| Task 2 - Database and Indexing | Database schema, constraints, foreign keys, soft deletes, and initial indexes |
| Task 3 - Performance Optimisation | Optimized order dashboard with eager loading, pagination capped at 50 records, Redis cache TTL, and cache invalidation hook |
| Task 4 - Queue Design | Not implemented as runnable code due to time; queue design and trade-offs are documented |
| Task 5 - Code Review | Corrected product stock reduction, top-selling query, and active-product search |
| Task 6 - Unit and Feature Testing | Product stock unit tests and authenticated order placement feature tests |

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

#### 2a — Schema Implementation
The project includes migrations and Eloquent models for:

- users
- products
- sale events
- orders
- order logs

#### 2c — Written Explanation

Q1. The orders table has columns: id, user_id, product_id, sale_event_id, status, created_at. 
A common query is: "fetch all orders by a specific user, filtered by status, sorted by created_at DESC."
Would you use a single-column index on user_id, or a composite index?

Write the exact index and justify your choice =>

`I would use a composite index instead of a single-column index on 'user_id'
Exact index:`

```php
$table->index(['user_id', 'status', 'created_at'], 'idx_orders_user_status_created_at');
```
`This index matches the common query pattern:`
```sql
SELECT * FROM orders
WHERE user_id = ?
AND status = ?
ORDER BY created_at DESC;
```

`A single-column index on user_id would only help MySQL find orders for a specific user, but MySQL would still need to filter by status and may need an extra sort step for created_at DESC.`

`The composite index is better because user_id narrows the result set first, status filters it further, and created_at helps MySQL return the latest orders more efficiently.`

Q2. Give one real example (from any table in this project) where adding an index could cause a
deadlock or make things worse under concurrent writes. Explain the mechanism. =>

`A real example in this project is adding extra standalone indexes on high-write order columns such as 'orders.status' or 'orders.created_at' without a query that benefits from them. During a flash sale, many concurrent requests insert orders and update product stock. Every secondary index on 'orders' must also be maintained for each insert, so InnoDB has to update more B-tree pages, hold more index page locks, write more redo/undo data, and potentially split hot index pages.`

`This may not directly create a deadlock by itself, but it increases lock duration and write amplification. If concurrent transactions touch tables in different orders, for example one flow inserts an order then writes an order log while another writes an order log then updates an order status, the extra index maintenance can make those locks overlap longer and make deadlocks more likely. A standalone status index is also low-cardinality, so MySQL may ignore it for reads while still paying the write cost on every order insert or status update.`

Q3. Give one example of an index that would be useless or counterproductive in this schema (e.g.,
low-cardinality columns, redundant coverage). Explain why MySQL/InnoDB would likely ignore it. =>
`An example of a counterproductive index in this schema would be a standalone index on 'orders.status'`
`This index would be counterproductive because status is a low-cardinality column with only a few distinct values. MySQL/InnoDB would likely ignore it because the index would not provide significant performance benefits and could add unnecessary overhead during write operations.`

Q4. When is a covering index useful? Give a specific query from this project where you would apply
one. =>

`A covering index is useful when a frequently executed query can be answered directly from the index without reading the full table rows. This is helpful for read-heavy endpoints such as order history, dashboards, or reporting pages where the query only needs a small set of columns.`

`A specific query from this project is the user order history endpoint:`

```sql
SELECT id, user_id, status, created_at
FROM orders
WHERE user_id = ?
AND status = ?
ORDER BY created_at DESC
LIMIT 50;
```
`For this query, I would apply the following covering index:`

```php
$table->index(['user_id', 'status', 'created_at', 'id'], 'idx_orders_user_status_created_id');
``` 

`This index supports the filter by 'user_id' and 'status', supports sorting by 'created_at', and includes 'id' so that the selected columns can be read from the index itself.`

## Task 3: Performance Optimisation

### Problems in the original code

1. `SaleEvent::all()` loads all sale events into memory.
2. Querying orders inside the event loop creates N+1 queries.
3. `User::find()` inside the order loop creates another N+1 query.
4. `Product::find()` inside the order loop creates another N+1 query.
5. No pagination, so the endpoint can return unlimited records.
6. Loads full Eloquent models even though only a few columns are needed.
7. `$total` and `$revenue` are calculated but not returned, causing wasted CPU work.
8. Revenue calculation uses product price instead of order unit_price, which may be incorrect if product price changes.
9. No caching, so repeated dashboard calls hit the database every time.
10. No cache invalidation when new orders are placed.
11. No query logging or measurement, so performance improvement cannot be verified.

### Implemented Optimisation

- Order dashboard uses eager loading for sale event, user, and product data.
- Pagination is capped at 50 records per request.
- The requested page is passed explicitly into Laravel pagination instead of relying on the current HTTP request.
- Dashboard responses are cached in Redis using the `orders-dashboard` cache tag.
- `OrderObserver` flushes the dashboard cache after order create, update, delete, restore, and force delete events. The observer handles events after database commit, so order placement invalidates the cache only after a successful committed create.

## Task 4 - Queue Design

Task 4 is not implemented as runnable queue code due to time. The design decision is documented here instead.

For a production flash-sale workload, order placement should keep the critical request path small: validate the active sale event, atomically decrement stock, create the order, and return the result. Non-critical follow-up work should move to queues, for example sending confirmation email, writing analytics events, syncing external systems, and generating operational reports.

Recommended queue design:

- Use Redis queues with separate queues such as `orders`, `notifications`, and `analytics` so slow external work does not block order processing.
- Dispatch jobs only after database commit to avoid processing rolled-back orders.
- Make jobs idempotent by using `order_id` as the stable key and guarding against duplicate side effects.
- Use retries with bounded backoff for transient failures, and a failed-jobs table or dead-letter workflow for manual review.
- Keep stock decrement and duplicate-order protection synchronous in the database because those are correctness-critical and should not depend on eventual queue processing.

## Task 5 - Code Review (Implemented Corrections)

### Merge Request Review Comments

`reduceStock`

- Problem: The original code used `Product::find()` and then updated `$product->stock` in PHP. Risk: two concurrent requests can both read the same stock value and oversell the product.
- Recommended fix: validate that quantity is positive and use one atomic database update guarded by `WHERE stock >= ?`. The implemented repository method returns success only when one row is decremented.

`getTopSellingProducts`

- Problem: The original code loaded every order into memory and counted products in PHP. Risk: memory usage and response time grow with the full orders table, which is unsafe for a flash-sale workload.
- Recommended fix: aggregate in the database, filter to paid orders, group by product, sort by order count, and limit the result set.

`searchProducts`

- Problem: The original code concatenated user input into raw SQL. Risk: SQL injection, no active-product filtering, and no result bound.
- Recommended fix: trim the keyword and use Eloquent's parameter binding through `where('name', 'like', ...)`, return active products only, and apply a limit.

The existing product service and repository provide:

- positive-quantity validation before stock reduction
- atomic stock decrement guarded by `stock >= quantity`
- paid-order aggregation for top-selling products
- active-product listing
- trimmed active-product name search
- bounded results through method limit parameters

## Task 6 - Unit and Feature Testing

Implemented tests cover:

- `ProductService::reduceStock` success
- insufficient stock
- a mocked concurrent decrement race where stock is consumed before the atomic update
- invalid non-positive quantity
- order placement happy path
- out-of-stock order placement
- unauthenticated order placement
- duplicate order in the same sale event

Tests use `RefreshDatabase` with SQLite in-memory for database-backed feature coverage.

## Test Evidence

Run:

```sh
make test
```

Verified on June 8, 2026:

```text
Tests: 38 passed (142 assertions)
```
