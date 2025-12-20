# Shared Parking System

A Clean Architecture PHP-based parking management system that enables parking space owners to rent out their unused spaces. Built as a HETIC 3rd year project (2025) demonstrating strict adherence to Clean Architecture and SOLID principles.

## Table of Contents

- [Features](#features)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [API Documentation](#api-documentation)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Business Rules](#business-rules)

## Features

### User Features
- User registration and JWT authentication
- Search parkings by GPS location with distance filtering
- Make reservations with real-time availability checking
- Enter/exit parking with validation
- View reservation history and generate invoices
- Subscribe to recurring parking slots

### Owner Features
- Owner registration and authentication
- Create and manage parking spaces
- Set hourly rates and opening hours
- View parking statistics and revenue
- Monitor unauthorized users
- Manage subscriptions

### Technical Features
- Pure PHP 8.x (no frameworks)
- Clean Architecture with strict layer separation
- Dual storage: MySQL + File-based repositories
- JWT authentication with Firebase JWT
- 15-minute billing increments
- Overstay penalty system (20 EUR)
- HTML invoice generation
- Comprehensive test suite (134 tests)

## Architecture

This project follows Clean Architecture principles with three distinct layers:

```
+-------------------+
|  Infrastructure   |  Controllers, Repositories, External Services
+-------------------+
         |
         v
+-------------------+
|    Use Cases      |  Business Logic, Request/Response DTOs
+-------------------+
         |
         v
+-------------------+
|     Domain        |  Entities, Repository Interfaces, Value Objects
+-------------------+
```

### Layer Rules
- Domain Layer: Pure business entities with NO external dependencies
- Use Case Layer: Business logic independent of frameworks and databases
- Infrastructure Layer: All external concerns (HTTP, database, external APIs)
- Dependencies point inward only (Infrastructure -> UseCase -> Domain)

## Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Composer
- Node.js 18+ (for frontend)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-repo/parking-project.git
cd parking-project
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Frontend Dependencies

```bash
cd frontend
npm install
cd ..
```

## Configuration

### 1. Create Environment File

Copy the example environment file:

```bash
cp .env.example .env
```

### 2. Configure Database

Edit `.env` with your MySQL credentials:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=parking_system
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Configure JWT

Set a secure JWT secret (minimum 32 characters):

```env
JWT_SECRET=your-secure-secret-key-minimum-32-characters
JWT_EXPIRATION=3600
```

### 4. Create Database

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS parking_system;"
```

### 5. Run Migrations

```bash
php bin/migrate.php up
```

### 6. Seed Test Data (Optional)

```bash
php bin/seed.php
```

This creates:
- 3 parking owners (owner1@parking.com / password123)
- 5 parkings across French cities
- 3 test users (alice@example.com / password123)

## Running the Application

### Start Backend Server

```bash
php -S localhost:8000 -t public
```

### Start Frontend Development Server

```bash
cd frontend
npm run dev
```

The frontend will be available at http://localhost:5173

### Production Build

```bash
cd frontend
npm run build
```

## API Documentation

### Authentication

All protected endpoints require a JWT token in the Authorization header:

```
Authorization: Bearer <token>
```

### Endpoints

#### Users

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/users | No | Register new user |
| POST | /api/auth/login | No | Authenticate user |
| GET | /api/users/profile | User | Get user profile |

#### Owners

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/owners | No | Register new owner |
| POST | /api/owners/login | No | Authenticate owner |
| GET | /api/owners/profile | Owner | Get owner profile |
| PUT | /api/owners/profile | Owner | Update owner profile |

#### Parkings

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /api/parkings | No | List all parkings |
| GET | /api/parkings?latitude=X&longitude=Y | No | Search by location |
| GET | /api/parkings/:id | No | Get parking details |
| POST | /api/parkings | Owner | Create parking |
| PUT | /api/parkings/:id | Owner | Update parking |
| DELETE | /api/parkings/:id | Owner | Delete parking |

#### Reservations

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/reservations | User | Create reservation |
| GET | /api/reservations | User | List user reservations |
| GET | /api/reservations/:id | User | Get reservation details |
| DELETE | /api/reservations/:id | User | Cancel reservation |
| GET | /api/reservations/:id/invoice | User | Generate invoice |
| GET | /api/owner/reservations | Owner | List owner's parking reservations |

#### Sessions

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/sessions | User | Enter parking |
| PUT | /api/sessions/:id/end | User | Exit parking |
| GET | /api/sessions | User | List user sessions |
| GET | /api/sessions/:id | User | Get session details |

#### Subscriptions

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/subscriptions | User | Create subscription |
| GET | /api/subscriptions | User | List user subscriptions |
| GET | /api/subscriptions/:id | User | Get subscription details |
| DELETE | /api/subscriptions/:id | User | Cancel subscription |
| GET | /api/parkings/:id/subscriptions | No | List parking subscriptions |

### Example Requests

#### Register User

```bash
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

#### Create Reservation

```bash
curl -X POST http://localhost:8000/api/reservations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "parkingId": "parking-paris-001",
    "startTime": "2025-01-15T10:00:00Z",
    "endTime": "2025-01-15T14:00:00Z"
  }'
```

#### Search Parkings by Location

```bash
curl "http://localhost:8000/api/parkings?latitude=48.8566&longitude=2.3522&maxDistance=10"
```

## Testing

### Run All Tests

```bash
composer test
```

### Run Specific Test Suite

```bash
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Functional
```

### Test Coverage

The project includes 134 tests covering:
- Unit tests for all use cases
- Functional tests for user and owner journeys
- Domain entity validation
- Business rule enforcement

## Project Structure

```
parking-project/
├── bin/
│   ├── migrate.php          # Database migration CLI
│   └── seed.php             # Database seeder
├── public/
│   └── index.php            # Application entry point
├── src/
│   ├── Domain/
│   │   ├── Entity/          # Business entities (User, Parking, Reservation, etc.)
│   │   ├── Repository/      # Repository interfaces
│   │   └── ValueObject/     # Value objects (GpsCoordinates, etc.)
│   ├── UseCase/
│   │   ├── User/            # User-related use cases
│   │   ├── Parking/         # Parking management use cases
│   │   ├── ParkingOwner/    # Owner use cases
│   │   ├── Reservation/     # Reservation use cases
│   │   ├── Session/         # Parking session use cases
│   │   ├── Subscription/    # Subscription use cases
│   │   └── Analytics/       # Statistics and reporting
│   └── Infrastructure/
│       ├── Http/
│       │   ├── Controller/  # HTTP controllers
│       │   ├── Middleware/  # Authentication middleware
│       │   ├── Routing/     # Router implementation
│       │   └── routes.php   # Route definitions
│       ├── Repository/
│       │   ├── MySQL/       # MySQL implementations
│       │   └── File/        # File-based implementations
│       ├── Service/         # External service implementations
│       └── Migration/       # Database migrations
├── tests/
│   ├── Unit/                # Unit tests
│   └── Functional/          # Functional journey tests
├── frontend/                # React frontend application
├── composer.json
├── phpunit.xml
└── .env.example
```

## Business Rules

### Pricing

- Billing is calculated in 15-minute increments (rounded up)
- Hourly rate is set per parking by the owner
- Subscriptions receive a 20% discount on calculated rates

### Overstay Penalty

- Fixed penalty of 20 EUR for exceeding reservation time
- Additional time charged at hourly rate (15-minute increments)

### Reservations

- Must be made for future times only
- Minimum duration: 30 minutes
- Maximum duration: 24 hours
- Must fall within parking opening hours
- Conflict checking prevents double-booking

### Subscriptions

- Minimum duration: 1 month
- Maximum duration: 12 months
- Weekly recurring time slots
- Slot conflict checking with existing subscriptions

### Access Control

- Entry requires valid reservation or active subscription
- Exit calculates final amount including any overstay
- Sessions track entry/exit times for billing

## Authors

- Maxime THEOPHILOS
- Wissem KARBOUB

## License

This project is developed for educational purposes as part of the HETIC curriculum.
