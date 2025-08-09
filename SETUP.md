# 🚀 Laravel SaaS Modular - Setup Guide

This guide will help you get the Laravel SaaS Modular system up and running on your local development environment.

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- **Docker** (v20.10+) and **Docker Compose** (v2.0+)
- **Git** (v2.25+)
- **Make** (optional, but highly recommended)

### Verify Prerequisites

```bash
# Check Docker
docker --version
docker-compose --version

# Check Git
git --version

# Check Make (optional)
make --version
```

## ⚡ Quick Setup (Recommended)

### 1. Clone the Repository

```bash
git clone https://github.com/cupyaz/laravel-saas-modular.git
cd laravel-saas-modular
```

### 2. Run One-Command Setup

```bash
make setup
```

This single command will:
- ✅ Build Docker containers
- ✅ Install PHP dependencies (Composer)
- ✅ Install Node.js dependencies (NPM)
- ✅ Create .env file from template
- ✅ Generate application key
- ✅ Run database migrations
- ✅ Seed sample data
- ✅ Create storage symlinks
- ✅ Install Git hooks for code quality

### 3. Start Development

```bash
make dev-start
```

Your application is now ready! 🎉

**Access your application:**
- 🌐 **Main Application**: http://localhost
- 📧 **Email Testing (MailHog)**: http://localhost:8025
- 🔥 **Hot Reload Dev Server**: http://localhost:5173

## 🛠️ Manual Setup (Step by Step)

If you prefer to understand each step or need to troubleshoot:

### Step 1: Clone and Navigate

```bash
git clone https://github.com/cupyaz/laravel-saas-modular.git
cd laravel-saas-modular
```

### Step 2: Build Docker Containers

```bash
# Build all containers
make build

# Or without Make
docker-compose build --no-cache
```

### Step 3: Start Services

```bash
# Start all services
make up

# Or without Make
docker-compose up -d
```

### Step 4: Install Dependencies

```bash
# Install PHP dependencies
make install

# Or manually
docker-compose exec app composer install
docker-compose exec node npm install
```

### Step 5: Environment Configuration

```bash
# Copy environment file
make env

# Or manually
cp .env.example .env
```

### Step 6: Generate Application Key

```bash
make key

# Or manually
docker-compose exec app php artisan key:generate
```

### Step 7: Database Setup

```bash
# Run migrations and seeders
make migrate-seed

# Or manually
docker-compose exec app php artisan migrate --seed
```

### Step 8: Storage Setup

```bash
# Create storage symlinks
make storage

# Or manually
docker-compose exec app php artisan storage:link
```

### Step 9: Install Git Hooks (Optional but Recommended)

```bash
make hooks
```

### Step 10: Start Development Server

```bash
# Start frontend development server
make dev

# Or manually
docker-compose exec node npm run dev
```

## 🔧 Configuration

### Environment Variables

Key configuration options in `.env`:

```env
# Application
APP_NAME="Laravel SaaS Modular"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_saas
DB_USERNAME=laravel
DB_PASSWORD=laravel_password

# Cache & Sessions
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=redis

# Email (Development)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025

# Stripe (for testing)
STRIPE_KEY=pk_test_your_key_here
STRIPE_SECRET=sk_test_your_secret_here
```

### Development URLs

After setup, these services will be available:

| Service | URL | Description |
|---------|-----|-------------|
| Web Application | http://localhost | Main Laravel application |
| API | http://localhost/api | REST API endpoints |
| MailHog | http://localhost:8025 | Email testing interface |
| Vite Dev Server | http://localhost:5173 | Hot reload dev server |

### Database Access

Connect to your database using these credentials:

- **Host**: localhost
- **Port**: 3306
- **Database**: laravel_saas
- **Username**: laravel
- **Password**: laravel_password

## 🧪 Verify Installation

### 1. Check Application Health

```bash
# Check if services are running
make status

# Test application health
make health

# Or manually test endpoints
curl http://localhost/health
curl http://localhost/api/v1/health
```

### 2. Run Tests

```bash
# Run the test suite
make test

# Run tests with coverage
make test-coverage
```

### 3. Check Code Quality

```bash
# Run all quality checks
make quality

# Individual checks
make format-check
make analyse
make cs-check
```

## 🚨 Troubleshooting

### Common Issues

#### 1. Port Already in Use

If you get port conflicts:

```bash
# Check what's using the ports
lsof -i :80
lsof -i :3306
lsof -i :6379

# Stop conflicting services or change ports in docker-compose.yml
```

#### 2. Permission Issues

```bash
# Fix file permissions
make permissions

# Or manually
sudo chmod -R 775 storage bootstrap/cache
```

#### 3. Docker Issues

```bash
# Clean Docker environment
make clean

# Remove everything and start fresh
make clean-all
make setup
```

#### 4. Database Connection Issues

```bash
# Check MySQL container status
docker-compose logs mysql

# Restart MySQL container
docker-compose restart mysql

# Reset database
make db-reset
```

#### 5. Composer/NPM Issues

```bash
# Clear and reinstall dependencies
docker-compose exec app composer clear-cache
docker-compose exec app composer install

docker-compose exec node npm cache clean --force
docker-compose exec node npm install
```

### Getting Help

If you encounter issues:

1. Check the logs: `make logs`
2. Verify container status: `make status`
3. Try a fresh installation: `make clean-all && make setup`
4. Check our [GitHub Issues](https://github.com/cupyaz/laravel-saas-modular/issues)

## 🔄 Development Workflow

### Daily Development

```bash
# Start your day
make up
make dev

# Work on your features...

# Before committing (optional, hooks do this automatically)
make quality
make test

# End of day
make down
```

### Working with Database

```bash
# Reset database with fresh data
make db-reset

# Connect to database
make db-connect

# Export database
make db-dump

# Import database
make db-restore
```

### Code Quality

```bash
# Format your code
make format

# Check for issues
make analyse

# Run tests
make test
```

## 📚 Next Steps

After successful setup:

1. 📖 Read the [main README](README.md) for detailed documentation
2. 🏗️ Explore the modular architecture
3. 🧪 Run the test suite to understand the codebase
4. 🎨 Customize the frontend components
5. 🔧 Create your first module

## 🎉 You're Ready!

Congratulations! Your Laravel SaaS Modular development environment is ready.

**Quick reference:**
- `make help` - See all available commands
- `make dev` - Start frontend development server
- `make test` - Run tests
- `make shell` - Access application container
- `make logs` - View container logs

Happy coding! 🚀