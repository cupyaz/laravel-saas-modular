# Laravel SaaS Modular - Development Makefile
# 
# This Makefile provides convenient commands for development tasks
# Make sure Docker and Docker Compose are installed

.DEFAULT_GOAL := help
.PHONY: help

# Variables
DOCKER_COMPOSE = docker compose
DOCKER_APP = $(DOCKER_COMPOSE) exec app
DOCKER_NODE = $(DOCKER_COMPOSE) exec node
DOCKER_DB = $(DOCKER_COMPOSE) exec mysql

# Colors for output
YELLOW := \033[33m
GREEN := \033[32m
RED := \033[31m
RESET := \033[0m

## ==========================================
## 🚀 SETUP & INSTALLATION
## ==========================================

help: ## Show this help message
	@echo ""
	@echo "$(GREEN)Laravel SaaS Modular - Development Commands$(RESET)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(YELLOW)%-20s$(RESET) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@awk 'BEGIN {FS = "## "} /^## / { printf "\n$(GREEN)%s$(RESET)\n", $$2 }' $(MAKEFILE_LIST)
	@echo ""

setup: ## 🎯 Complete project setup (first time)
	@echo "$(GREEN)Setting up Laravel SaaS Modular project...$(RESET)"
	@make build
	@make install
	@make env
	@make key
	@make migrate-seed
	@make storage
	@make hooks
	@echo "$(GREEN)✅ Setup complete! Visit http://localhost$(RESET)"

build: ## 🏗️  Build Docker containers
	$(DOCKER_COMPOSE) build --no-cache

up: ## ⬆️  Start all services
	$(DOCKER_COMPOSE) up -d

down: ## ⬇️  Stop all services
	$(DOCKER_COMPOSE) down

restart: ## 🔄 Restart all services
	@make down
	@make up

logs: ## 📋 Show container logs
	$(DOCKER_COMPOSE) logs -f

install: ## 📦 Install dependencies
	@make up
	$(DOCKER_APP) composer install
	$(DOCKER_NODE) npm install

update: ## 🔄 Update dependencies
	$(DOCKER_APP) composer update
	$(DOCKER_NODE) npm update

## ==========================================
## 🛠️  LARAVEL COMMANDS
## ==========================================

env: ## 📝 Copy .env.example to .env
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "$(GREEN)✅ .env file created$(RESET)"; \
	else \
		echo "$(YELLOW)⚠️  .env file already exists$(RESET)"; \
	fi

key: ## 🔑 Generate application key
	$(DOCKER_APP) php artisan key:generate

migrate: ## 🗃️  Run database migrations
	$(DOCKER_APP) php artisan migrate

migrate-fresh: ## 🆕 Fresh migrations (WARNING: Drops all tables)
	$(DOCKER_APP) php artisan migrate:fresh

migrate-seed: ## 🌱 Run migrations and seeders
	$(DOCKER_APP) php artisan migrate --seed

seed: ## 🌱 Run database seeders
	$(DOCKER_APP) php artisan db:seed

rollback: ## ↩️  Rollback last migration
	$(DOCKER_APP) php artisan migrate:rollback

storage: ## 🔗 Create storage symlinks
	$(DOCKER_APP) php artisan storage:link

cache-clear: ## 🧹 Clear application cache
	$(DOCKER_APP) php artisan cache:clear
	$(DOCKER_APP) php artisan config:clear
	$(DOCKER_APP) php artisan route:clear
	$(DOCKER_APP) php artisan view:clear

optimize: ## ⚡ Optimize application for production
	$(DOCKER_APP) php artisan config:cache
	$(DOCKER_APP) php artisan route:cache
	$(DOCKER_APP) php artisan view:cache

## ==========================================
## 🧪 TESTING & QUALITY
## ==========================================

test: ## 🧪 Run PHPUnit tests
	$(DOCKER_APP) php artisan test

test-coverage: ## 📊 Run tests with coverage
	$(DOCKER_APP) php artisan test --coverage-html coverage --coverage-clover coverage.xml

test-parallel: ## ⚡ Run tests in parallel
	$(DOCKER_APP) php artisan test --parallel

pest: ## 🐛 Run Pest tests (if installed)
	$(DOCKER_APP) ./vendor/bin/pest

format: ## 🎨 Format code with Laravel Pint
	$(DOCKER_APP) ./vendor/bin/pint

format-check: ## ✅ Check code formatting
	$(DOCKER_APP) ./vendor/bin/pint --test

analyse: ## 🔬 Run PHPStan static analysis
	$(DOCKER_APP) ./vendor/bin/phpstan analyse

cs-check: ## 📏 Check code standards
	$(DOCKER_APP) ./vendor/bin/phpcs

cs-fix: ## 🔧 Fix code standards
	$(DOCKER_APP) ./vendor/bin/phpcbf

security: ## 🔒 Security audit
	$(DOCKER_APP) composer audit

quality: ## ✨ Run all quality checks
	@make format-check
	@make analyse
	@make cs-check
	@make test

## ==========================================
## 🎨 FRONTEND COMMANDS
## ==========================================

npm-install: ## 📦 Install npm dependencies
	$(DOCKER_NODE) npm install

npm-update: ## 🔄 Update npm dependencies
	$(DOCKER_NODE) npm update

dev: ## 🔥 Start Vite dev server with hot reloading
	$(DOCKER_NODE) npm run dev

build-assets: ## 🏗️  Build production assets
	$(DOCKER_NODE) npm run build

watch: ## 👁️  Watch for asset changes
	$(DOCKER_NODE) npm run dev

## ==========================================
## 🗃️  DATABASE COMMANDS
## ==========================================

db-connect: ## 🔌 Connect to database
	$(DOCKER_DB) mysql -u laravel -p laravel_saas

db-dump: ## 💾 Dump database
	$(DOCKER_COMPOSE) exec mysql mysqldump -u laravel -p laravel_saas > dump.sql

db-restore: ## 📥 Restore database from dump.sql
	@if [ ! -f dump.sql ]; then \
		echo "$(RED)❌ dump.sql file not found$(RESET)"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) exec -T mysql mysql -u laravel -p laravel_saas < dump.sql

db-reset: ## 🔄 Reset database (fresh migrations + seeding)
	@make migrate-fresh
	@make seed

## ==========================================
## 🛠️  DEVELOPMENT TOOLS
## ==========================================

shell: ## 🐚 Access app container shell
	$(DOCKER_APP) bash

shell-node: ## 🟢 Access node container shell
	$(DOCKER_NODE) sh

shell-db: ## 🗃️  Access database shell
	$(DOCKER_DB) bash

logs-app: ## 📋 Show app container logs
	$(DOCKER_COMPOSE) logs -f app

logs-nginx: ## 📋 Show nginx container logs
	$(DOCKER_COMPOSE) logs -f nginx

logs-mysql: ## 📋 Show mysql container logs
	$(DOCKER_COMPOSE) logs -f mysql

ide-helper: ## 🧠 Generate IDE helper files
	$(DOCKER_APP) php artisan ide-helper:generate
	$(DOCKER_APP) php artisan ide-helper:meta
	$(DOCKER_APP) php artisan ide-helper:models --write

hooks: ## 🪝 Install Git hooks for code quality
	@if [ -d .git ]; then \
		cp .githooks/* .git/hooks/ 2>/dev/null || true; \
		chmod +x .git/hooks/* 2>/dev/null || true; \
		echo "$(GREEN)✅ Git hooks installed$(RESET)"; \
	else \
		echo "$(YELLOW)⚠️  Not a Git repository$(RESET)"; \
	fi

## ==========================================
## 🧹 MAINTENANCE
## ==========================================

clean: ## 🧹 Clean up containers and volumes
	$(DOCKER_COMPOSE) down -v
	docker system prune -f

clean-all: ## 💥 Clean everything (containers, images, volumes)
	$(DOCKER_COMPOSE) down -v --rmi all
	docker system prune -af

status: ## 📊 Show service status
	$(DOCKER_COMPOSE) ps

health: ## 🏥 Check application health
	@echo "$(YELLOW)Checking services...$(RESET)"
	@curl -s http://localhost/health || echo "$(RED)❌ Web service not responding$(RESET)"
	@curl -s http://localhost:8025 > /dev/null && echo "$(GREEN)✅ Mailhog available$(RESET)" || echo "$(RED)❌ Mailhog not available$(RESET)"

permissions: ## 🔐 Fix file permissions
	@echo "$(YELLOW)Fixing permissions...$(RESET)"
	@if [ -d storage ]; then \
		chmod -R 775 storage; \
		chmod -R 775 bootstrap/cache; \
		echo "$(GREEN)✅ Permissions fixed$(RESET)"; \
	fi

## ==========================================
## 📚 DOCUMENTATION
## ==========================================

docs: ## 📖 Generate API documentation
	$(DOCKER_APP) php artisan l5-swagger:generate

serve-docs: ## 🌐 Serve documentation
	@echo "$(GREEN)Documentation available at: http://localhost/api/documentation$(RESET)"

## ==========================================
## 🚀 DEPLOYMENT
## ==========================================

deploy-prep: ## 🎯 Prepare for deployment
	@make quality
	@make optimize
	@make build-assets
	@echo "$(GREEN)✅ Ready for deployment$(RESET)"

prod-build: ## 🏭 Build for production
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml build

## ==========================================
## 🎯 QUICK COMMANDS
## ==========================================

quick-start: ## ⚡ Quick start (build + up + install)
	@make build
	@make up
	@make install
	@echo "$(GREEN)✅ Quick start complete!$(RESET)"

fresh-start: ## 🆕 Fresh start (clean + setup)
	@make clean
	@make setup

dev-start: ## 🔥 Start development (up + dev server)
	@make up
	@echo "$(YELLOW)Starting development server...$(RESET)"
	@make dev