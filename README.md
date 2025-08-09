# Laravel SaaS Modular

A complete, modular Laravel SaaS framework designed for freemium projects with mobile-first focus, multi-tenancy support, and integrated subscription management.

## 🚀 Features

- **🏢 Multi-Tenant Architecture**: Support for both single-database and multi-database tenancy
- **💳 Subscription Management**: Integrated Stripe billing with Laravel Cashier
- **🧩 Modular Design**: Organize features into reusable modules
- **🐳 Docker Development**: Complete Docker-based development environment
- **🔥 Hot Reloading**: Vite-powered frontend with hot module replacement
- **🧪 Testing Framework**: Comprehensive PHPUnit testing setup
- **📊 Code Quality**: Automated code formatting, static analysis, and quality checks
- **🪝 Git Hooks**: Pre-commit and pre-push quality gates
- **📱 Mobile-First**: Responsive design with Tailwind CSS
- **🔒 Security**: Built-in security features and audit tools

## 📋 Requirements

- Docker & Docker Compose
- Git
- Make (optional, but recommended)

## 🎯 Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/cupyaz/laravel-saas-modular.git
cd laravel-saas-modular
```

### 2. One-Command Setup

```bash
make setup
```

This command will:
- Build Docker containers
- Install PHP and Node.js dependencies
- Set up environment configuration
- Generate application key
- Run database migrations and seeders
- Create storage symlinks
- Install Git hooks

### 3. Start Development

```bash
make dev-start
```

Your application will be available at:
- **Web Application**: http://localhost
- **API**: http://localhost/api
- **Mailhog (Email Testing)**: http://localhost:8025

## 📁 Project Structure

```
laravel-saas-modular/
├── app/                    # Core application files
├── modules/               # Feature modules
│   └── [ModuleName]/
│       ├── Controllers/
│       ├── Models/
│       ├── Views/
│       ├── routes/
│       └── database/
├── docker/               # Docker configuration
│   ├── app/
│   ├── nginx/
│   ├── mysql/
│   └── node/
├── database/            # Migrations, seeders, factories
├── resources/           # Frontend assets and views
├── tests/              # Application tests
├── .githooks/          # Git hooks for quality control
└── Makefile           # Development commands
```

## 🛠️ Development Commands

The project includes a comprehensive Makefile with all necessary development commands:

### Essential Commands

```bash
make help           # Show all available commands
make setup          # Complete project setup
make up            # Start all services
make down          # Stop all services  
make logs          # Show container logs
make shell         # Access app container
```

### Laravel Commands

```bash
make migrate       # Run migrations
make migrate-seed  # Run migrations with seeders
make seed          # Run seeders only
make cache-clear   # Clear application cache
make optimize      # Optimize for production
```

### Testing & Quality

```bash
make test          # Run PHPUnit tests
make test-coverage # Run tests with coverage
make format        # Format code with Pint
make analyse       # Run PHPStan static analysis
make quality       # Run all quality checks
```

### Frontend Development

```bash
make dev           # Start Vite dev server with hot reloading
make build-assets  # Build production assets
make npm-install   # Install Node.js dependencies
```

### Database Management

```bash
make db-connect    # Connect to database
make db-dump       # Export database
make db-restore    # Import database from dump
make db-reset      # Fresh migrations + seeders
```

## 🏗️ Architecture

### Multi-Tenancy

The system supports flexible multi-tenancy:

1. **Single Database**: All tenants share the same database with proper isolation
2. **Multi Database**: Each tenant has its own database (configurable)

### Module System

Features are organized into self-contained modules:

```php
modules/
├── UserManagement/
│   ├── Controllers/
│   ├── Models/
│   ├── Providers/UserManagementServiceProvider.php
│   ├── routes/web.php
│   ├── routes/api.php
│   ├── database/migrations/
│   └── resources/views/
└── Billing/
    ├── Controllers/
    ├── Models/
    ├── Services/StripeService.php
    └── ...
```

### Service Providers

Modules are automatically loaded via the `ModuleServiceProvider`:

- Routes are auto-discovered
- Views are namespaced
- Migrations are auto-loaded
- Service providers are auto-registered

## 🧪 Testing

### Running Tests

```bash
# Run all tests
make test

# Run tests with coverage
make test-coverage

# Run tests in parallel
make test-parallel

# Run specific test
./vendor/bin/phpunit tests/Feature/ExampleTest.php
```

### Test Structure

```
tests/
├── Feature/        # Feature tests (HTTP, database integration)
├── Unit/          # Unit tests (isolated component testing)
└── TestCase.php   # Base test class
```

Each module can have its own test directory:

```
modules/UserManagement/tests/
├── Feature/
└── Unit/
```

## 📊 Code Quality

The project includes comprehensive code quality tools:

### Static Analysis

- **PHPStan**: Static analysis with Laravel rules
- **Laravel Pint**: Code formatting based on Laravel standards
- **PHP CodeSniffer**: Code standards enforcement

### Git Hooks

Pre-commit hooks run automatically:
- PHP syntax checking
- Code formatting validation
- Static analysis
- Quick test suite

Pre-push hooks include:
- Full test suite with coverage
- Comprehensive static analysis
- Security audit
- Large file detection

### Manual Quality Checks

```bash
make quality      # Run all quality checks
make format      # Auto-format code
make analyse     # Static analysis
make security    # Security audit
```

## 🐳 Docker Environment

### Services

- **app**: PHP 8.3-FPM with Laravel
- **nginx**: Web server with SSL support
- **mysql**: Database server with test database
- **redis**: Caching and session storage
- **mailhog**: Email testing
- **node**: Frontend asset compilation

### Environment Files

- `.env.example`: Template for local development
- `.env.testing`: Testing environment configuration
- `docker-compose.yml`: Development services
- `docker-compose.prod.yml`: Production overrides

### Debugging

Xdebug is configured for development:
- Host: `host.docker.internal`
- Port: `9003`
- IDE Key: `VSCODE`

## 🔒 Security

### Built-in Security Features

- CSRF protection
- XSS protection
- SQL injection protection via Eloquent ORM
- Rate limiting
- Secure session handling
- Input validation and sanitization

### Security Auditing

```bash
make security     # Run Composer audit
```

### Environment Security

- Sensitive data in `.env` files (never committed)
- Separate configurations for different environments
- Database credentials isolation
- API key management

## 📱 Frontend Development

### Technology Stack

- **Vite**: Fast build tool with hot reloading
- **Vue.js 3**: Progressive framework
- **Tailwind CSS**: Utility-first CSS framework
- **Alpine.js**: Lightweight reactive framework
- **Inertia.js**: Modern monolith approach

### Hot Reloading

Development server with hot module replacement:

```bash
make dev    # Start dev server on http://localhost:5173
```

Assets are automatically recompiled when files change.

### Production Build

```bash
make build-assets    # Build optimized assets for production
```

## 🗃️ Database

### Migrations

```bash
# Create migration
docker-compose exec app php artisan make:migration create_example_table

# Run migrations
make migrate

# Fresh migrations (drops all tables)
make migrate-fresh

# Rollback
make rollback
```

### Seeders

Sample data for development:

```bash
# Run all seeders
make seed

# Run specific seeder
docker-compose exec app php artisan db:seed --class=UserSeeder
```

### Multi-Tenant Migrations

Tenant-specific migrations are supported:

```bash
# Run tenant migrations
docker-compose exec app php artisan tenants:migrate

# Migrate specific tenant
docker-compose exec app php artisan tenants:migrate --tenant=1
```

## 🚀 Deployment

### Preparation

```bash
make deploy-prep    # Run quality checks and optimize
```

### Production Build

```bash
make prod-build     # Build production Docker images
```

### Environment Variables

Key production environment variables:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_HOST=your-production-db-host
DB_DATABASE=your-production-database
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password

# Stripe
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...

# Email
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Run quality checks: `make quality`
5. Run tests: `make test`
6. Commit: `git commit -m 'Add amazing feature'`
7. Push: `git push origin feature/amazing-feature`
8. Submit a pull request

### Code Standards

- Follow PSR-12 coding standards
- Write meaningful commit messages
- Add tests for new features
- Update documentation
- Ensure all quality checks pass

## 📖 Documentation

### API Documentation

```bash
make docs           # Generate API documentation
make serve-docs     # View at http://localhost/api/documentation
```

### Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Guide](https://vuejs.org/guide/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Docker Documentation](https://docs.docker.com/)

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 💬 Support

- 📧 Email: support@example.com
- 🐛 Issues: [GitHub Issues](https://github.com/cupyaz/laravel-saas-modular/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/cupyaz/laravel-saas-modular/discussions)

## 🙏 Acknowledgments

Built with these amazing technologies:
- [Laravel](https://laravel.com/)
- [Vue.js](https://vuejs.org/)
- [Docker](https://www.docker.com/)
- [Tailwind CSS](https://tailwindcss.com/)
- [Stripe](https://stripe.com/)

---

**Happy coding! 🚀**