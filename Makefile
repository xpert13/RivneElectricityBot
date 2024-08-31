# Define the Docker Compose file to use (if not default)
COMPOSE_FILE=./docker/docker-compose.yml

# Targets
.PHONY: build start stop restart restart.all migrate fixtures bash

build:
	@echo "Building Docker images..."
	docker-compose -f $(COMPOSE_FILE) build

start:
	@echo "Starting Docker containers..."
	docker-compose -f $(COMPOSE_FILE) up -d
stop:
	@echo "Stopping Docker containers..."
	docker-compose -f $(COMPOSE_FILE) stop

restart:
	@$(MAKE) stop
	@$(MAKE) start

bash:
	@echo "Enter into php-fpm container..."
	docker-compose -f $(COMPOSE_FILE) exec worker bash
