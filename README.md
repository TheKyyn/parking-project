# Shared Parking System

A Clean Architecture PHP-based parking management system that enables parking space owners to rent out their unused spaces on an hourly basis.

## Architecture

This project follows **Clean Architecture** principles with strict layer separation:

- **Domain Layer**: Pure business entities and rules (no external dependencies)
- **Use Case Layer**: Business logic implementation (independent of frameworks)
- **Infrastructure Layer**: Controllers, repositories, views, external services

## Requirements

- PHP 8.0+
- MySQL database
- Secondary storage (File/NoSQL for reservations and sessions)

## Installation

1. Clone the repository
2. Install dependencies: `composer install`
3. Configure database connection
4. Run migrations: `php bin/migrate.php`
5. Start development server: `php -S localhost:8000 -t public/`

## Development

- Run tests: `composer test`
- Check code quality: `composer check`
- Static analysis: `composer phpstan`

## Project Structure

```
src/
├── Domain/
│   ├── Entity/          # Core business entities
│   ├── Repository/      # Repository interfaces
│   └── ValueObject/     # Value objects
├── UseCase/             # Business logic use cases
└── Infrastructure/      # External concerns
    ├── Controller/      # HTTP controllers
    ├── Repository/      # Repository implementations
    └── View/           # Presentation layer
```

## Contributing

Follow Clean Architecture principles:
1. Entities must have NO external dependencies
2. Use cases must NOT depend on infrastructure
3. Dependencies point inward only
4. Business logic stays in use cases, not controllers
# parking-project
