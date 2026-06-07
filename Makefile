.DEFAULT_GOAL := help

.PHONY: help init start stop restart status logs test

help:
	@echo "Available commands:"
	@echo "  make init     Build and start containers, then reset and seed the database"
	@echo "  make start    Start containers without resetting the database"
	@echo "  make stop     Stop containers without removing data"
	@echo "  make restart  Restart containers without resetting the database"
	@echo "  make status   Show container status"
	@echo "  make logs     Follow container logs"
	@echo "  make test     Run the Laravel test suite"

init:
	docker compose up -d --build
	docker compose exec -T app php artisan migrate:fresh --seed

start:
	docker compose up -d

stop:
	docker compose stop

restart:
	docker compose restart

status:
	docker compose ps

logs:
	docker compose logs -f

test:
	docker compose exec -T app php artisan test
