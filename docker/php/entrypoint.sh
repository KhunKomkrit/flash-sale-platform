#!/usr/bin/env sh
set -eu

PROJECT_DIR=/var/www/html

if [ ! -f "$PROJECT_DIR/artisan" ]; then
    bootstrap_dir="$(mktemp -d)"
    trap 'rm -rf "$bootstrap_dir"' EXIT

    echo "Laravel project not found; bootstrapping Laravel 10..."
    composer create-project --no-interaction --no-blocking \
        "laravel/laravel:^10.0" "$bootstrap_dir/example-app"

    # Preserve repository and Docker files that already exist at the project root.
    rsync -a --ignore-existing "$bootstrap_dir/example-app/" "$PROJECT_DIR/"
    rm -rf "$bootstrap_dir"
    trap - EXIT
elif [ ! -f "$PROJECT_DIR/vendor/autoload.php" ]; then
    echo "Composer dependencies not found; installing..."
    composer install --working-dir="$PROJECT_DIR" --no-interaction
fi

if [ ! -f "$PROJECT_DIR/.env" ] && [ -f "$PROJECT_DIR/.env.example" ]; then
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
fi

if [ -f "$PROJECT_DIR/artisan" ] && [ -f "$PROJECT_DIR/.env" ]; then
    if ! grep -Eq '^APP_KEY=.+$' "$PROJECT_DIR/.env"; then
        php "$PROJECT_DIR/artisan" key:generate --force
    fi
fi

mkdir -p "$PROJECT_DIR/storage/framework/cache" \
    "$PROJECT_DIR/storage/framework/sessions" \
    "$PROJECT_DIR/storage/framework/views" \
    "$PROJECT_DIR/storage/logs" \
    "$PROJECT_DIR/bootstrap/cache"

chmod -R ug+rwX "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

exec "$@"
