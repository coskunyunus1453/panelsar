PROJECT_NAME := panelsar
ENGINE_DIR := engine
PANEL_DIR := panel
FRONTEND_DIR := frontend
DOCKER_DIR := docker

.PHONY: help dev build test clean engine panel frontend docker

help:
	@echo "Panelsar - Hosting Control Panel"
	@echo "================================"
	@echo "make dev          - Start all development services"
	@echo "make build        - Build all components"
	@echo "make test         - Run all tests"
	@echo "make engine       - Build Go engine"
	@echo "make panel        - Start Laravel panel"
	@echo "make frontend     - Start React frontend"
	@echo "make docker-up    - Start Docker services"
	@echo "make docker-down  - Stop Docker services"
	@echo "make migrate      - Run database migrations"
	@echo "make seed         - Seed database"
	@echo "make clean        - Clean build artifacts"

# Development
dev: docker-up
	@echo "Starting all services..."
	cd $(FRONTEND_DIR) && npm run dev &
	cd $(PANEL_DIR) && php artisan serve --port=8000 &
	cd $(ENGINE_DIR) && go run cmd/panelsar-engine/main.go

# Engine
engine:
	cd $(ENGINE_DIR) && go build -o bin/panelsar-engine cmd/panelsar-engine/main.go

engine-dev:
	cd $(ENGINE_DIR) && go run cmd/panelsar-engine/main.go

engine-test:
	cd $(ENGINE_DIR) && go test ./...

# Panel
panel:
	cd $(PANEL_DIR) && php artisan serve --port=8000

panel-test:
	cd $(PANEL_DIR) && php artisan test

migrate:
	cd $(PANEL_DIR) && php artisan migrate

seed:
	cd $(PANEL_DIR) && php artisan db:seed

# Frontend
frontend:
	cd $(FRONTEND_DIR) && npm run dev

frontend-build:
	cd $(FRONTEND_DIR) && npm run build

frontend-test:
	cd $(FRONTEND_DIR) && npm test

# Docker
docker-up:
	docker compose -f $(DOCKER_DIR)/docker-compose.yml up -d

docker-down:
	docker compose -f $(DOCKER_DIR)/docker-compose.yml down

docker-build:
	docker compose -f $(DOCKER_DIR)/docker-compose.yml build

# Build all
build: engine frontend-build
	@echo "All components built successfully"

# Test all
test: engine-test panel-test frontend-test
	@echo "All tests passed"

# Clean
clean:
	rm -rf $(ENGINE_DIR)/bin
	rm -rf $(FRONTEND_DIR)/dist
	rm -rf $(FRONTEND_DIR)/node_modules
	rm -rf $(PANEL_DIR)/vendor
	@echo "Cleaned all build artifacts"
