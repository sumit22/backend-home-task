## Project Highlights

**Key Features:**
- ✅ **188 passing tests** - Run `docker-compose exec php php bin/phpunit`
- ✅ **Health checks working** - Test `curl http://localhost:8888/health/ready`
- ✅ **Rate limiting active** - Nginx-based, configured in `docker/nginx/default.conf`
- ✅ **12-Factor compliance** - Logs to stdout/stderr, env-based config, stateless design
- ✅ **Async processing** - RabbitMQ + Messenger for long-running scans
- ✅ **Provider abstraction** - Clean adapter pattern in `src/Service/Provider/`
- ✅ **Production-ready** - Docker health checks, structured logging, error handling

**Quick Verification:**
```bash
docker-compose up -d                           # Start services
docker-compose exec php php bin/phpunit        # Run tests (should pass)
curl http://localhost:8888/health/ready        # Check health (should return 200)
docker-compose logs nginx | grep "rate limit"  # Verify rate limiting is active
```

---

## Introduction
This is a vulnerability scanning platform backend built with **Symfony 7.3** and **Docker**. It provides API endpoints for managing code repositories, initiating security scans, and receiving notifications.

### Quick Start
```bash
# Start all services
docker-compose up -d

# Run database migrations
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Load initial data (providers)
docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Run tests (188 tests, 696 assertions)
docker-compose exec php php bin/phpunit

# Access the application
curl http://localhost:8888/health/ready
```

### Architecture Overview
- **Symfony 7.3** - PHP framework with API-first design
- **MySQL 8.0** - Primary database (port 3307)
- **RabbitMQ** - Async message processing (ports 5672, 15672)
- **Nginx** - Web server with rate limiting (port 8888)
- **Mailpit** - Email testing UI (ports 1025, 8025)
- **LocalStack** - AWS services emulator (port 4566)

### Service Ports
| Service | Host Port | Container Port | Access |
|---------|-----------|----------------|--------|
| **Nginx (API)** | 8888 | 80 | http://localhost:8888 |
| **MySQL** | 3307 | 3306 | mysql://localhost:3307 |
| **RabbitMQ AMQP** | 5672 | 5672 | amqp://localhost:5672 |
| **RabbitMQ Management** | 15672 | 15672 | http://localhost:15672 |
| **Mailpit SMTP** | 1025 | 1025 | smtp://localhost:1025 |
| **Mailpit Web UI** | 8025 | 8025 | http://localhost:8025 |
| **LocalStack** | 4566 | 4566 | http://localhost:4566 |

**Credentials** (see `.env` file):
- MySQL: root / docker
- RabbitMQ: rabbit / docker
- LocalStack: test / test

## Key Features

### 1. Health Monitoring
Kubernetes-compatible health check endpoints for container orchestration:
- **Liveness**: `GET /health/live` - Returns 200 if application is running
- **Readiness**: `GET /health/ready` - Returns 200 if dependencies (DB, filesystem) are available

```bash
curl http://localhost:8888/health/ready
# {"status":"ready","checks":{"database":true,"filesystem":true},"timestamp":"2025-11-02T12:00:00+00:00"}
```

### 2. Rate Limiting
Three-tier nginx-based rate limiting protects API endpoints:

| Endpoint | Limit | Burst | Use Case |
|----------|-------|-------|----------|
| `/api/*` | 100/min | 20 | General API calls |
| `/api/repositories/{id}/scans` | 20/min | 10 | Scan creation |
| `/api/scans/{id}/files` | 10/min | 5 | File uploads |

Rate-limited requests return `429 Too Many Requests` with JSON error message.

### 3. Async Processing
RabbitMQ message bus handles long-running scan operations:
- `async` transport - General async tasks
- `scan_status` transport - Scan status polling
- `messenger-worker` container processes queued jobs

### 4. Notification System
Multi-channel notifications via Symfony Notifier:
- **Email** - Sent to Mailpit (development) or SMTP (production)
- **Slack** - Chat notifications for scan results
- Configurable per rule action type

### 5. AWS Integration
LocalStack emulates AWS services for development:
- **S3** - File storage (bucket: `rule-engine-files`, auto-created)
- **Available services**: SQS, SNS, DynamoDB, Lambda, Secrets Manager

## Docker Environment

### Essential Commands
```bash
# Start services (detached mode)
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f nginx php

# Access PHP shell
docker-compose exec php bash

# Restart specific service
docker-compose restart nginx
```

### Health Status
```bash
# Check service health
docker-compose ps

# Test application health
curl http://localhost:8888/health/ready
```

## API Endpoints

### Core Endpoints
```bash
# Home / Health (basic)
GET http://localhost:8888/

# Liveness probe (K8s)
GET http://localhost:8888/health/live

# Readiness probe (K8s)
GET http://localhost:8888/health/ready
```

### Repository Management
```bash
# List repositories
GET /api/repositories

# Create repository
POST /api/repositories

# Get repository
GET /api/repositories/{id}

# Initiate scan
POST /api/repositories/{id}/scans
```

### Scan Management
```bash
# Upload scan files
POST /api/scans/{id}/files

# Get scan status
GET /api/scans/{id}

# Get vulnerabilities
GET /api/scans/{id}/vulnerabilities
```

**Rate Limits Apply** - See rate limiting configuration in nginx (100/min default, 20/min scans, 10/min uploads)

## Database Management

The application uses MySQL as the database backend. The database configuration is already set up in `.env` and `docker-compose.yaml`.

### Database Connection Details
- **Host**: `db` (within Docker network) or `localhost:3307` (from host machine)
- **Database**: `rule_engine`
- **Username**: `root`
- **Password**: `docker`

### Database Commands

#### Database Operations
```bash
# Create database (if not exists)
docker compose exec php php bin/console doctrine:database:create --if-not-exists

# Drop database (be careful!)
docker compose exec php php bin/console doctrine:database:drop --force
```

#### Migration Management
```bash
# Generate new migration from entity changes
docker compose exec php php bin/console make:migration

# Run pending migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Check migration status
docker compose exec php php bin/console doctrine:migrations:status

# Execute specific migration
docker compose exec php php bin/console doctrine:migrations:execute DoctrineMigrations\\VersionXXXXXXXXXXXXXX
```

#### Schema Operations
```bash
# Validate mapping and database schema
docker compose exec php php bin/console doctrine:schema:validate

# Show SQL needed to update schema (dry run)
docker compose exec php php bin/console doctrine:schema:update --dump-sql

# Update schema directly (not recommended for production)
docker compose exec php php bin/console doctrine:schema:update --force
```

#### Entity Management
```bash
# Create new entity
docker compose exec php php bin/console make:entity EntityName

# Generate repository class
docker compose exec php php bin/console make:repository EntityName
```

### Current Entities
The application includes the following entities:
- **Upload**: Stores file upload information with repository and commit context
- **ScanResult**: Stores scanning results linked to uploads
- **Vulnerability**: Stores vulnerability information found during scans

### Database Access
You can access the MySQL database directly using any MySQL client:
```bash
# From host machine
mysql -h 127.0.0.1 -P 3307 -u root -p rule_engine
# Password: docker

# From within Docker network
docker compose exec db mysql -u root -p rule_engine
# Password: docker
```

## Testing

### Test Suite
**PHPUnit 9.5** with **PCOV** for code coverage (configured in `docker/php/Dockerfile`)

```bash
# Run all tests (188 tests, 696 assertions)
docker-compose exec php php bin/phpunit

# Run specific test suite
docker-compose exec php php bin/phpunit tests/Service/
docker-compose exec php php bin/phpunit tests/Controller/

# Run with coverage report
docker-compose exec php php bin/phpunit --coverage-text

# Generate HTML coverage report
docker-compose exec php php bin/phpunit --coverage-html coverage
```

### Test Statistics
- **Total**: 188 tests, 696 assertions
- **Execution Time**: ~40-60 seconds
- **Memory Usage**: ~38 MB

### High-Coverage Components
- `ScanService` - 100% coverage (16 tests)
- `ScanController` - 100% coverage (12 tests)
- `RepositoryService` - Comprehensive service layer tests
- Health checks, message handlers, provider adapters

## Data Fixtures

The application uses Doctrine Fixtures for seeding initial data.

### Loading Fixtures

```bash
# Load all fixtures (will purge existing data)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Append fixtures without purging
docker compose exec php php bin/console doctrine:fixtures:load --append
```

### Available Fixtures

- **ProviderFixtures**: Seeds security scanning providers (Debricked, Snyk)

## Architecture & Design Decisions

### 12-Factor App Compliance
This application follows [12-Factor App](https://12factor.net/) methodology:
- ✅ **Config** - Environment-based configuration via `.env`
- ✅ **Dependencies** - Explicit declaration via `composer.json`
- ✅ **Build, Release, Run** - Docker containers provide immutable releases
- ✅ **Processes** - Stateless execution, state in MySQL/RabbitMQ
- ✅ **Port Binding** - Self-contained web server (nginx + PHP-FPM)
- ✅ **Concurrency** - Horizontal scaling via message workers
- ✅ **Disposability** - Fast startup/shutdown, graceful termination
- ✅ **Dev/Prod Parity** - Docker ensures environment consistency
- ✅ **Logs** - Structured logging to stdout/stderr via Monolog
- ✅ **Admin Processes** - Console commands for migrations, fixtures

### Key Technical Choices

**1. Nginx Rate Limiting over Application-Level**
- **Why**: Better performance, protects PHP-FPM pool, prevents resource exhaustion
- **Trade-off**: Less granular control (IP-based only, no API key-based limits)
- **Location**: `docker/nginx/default.conf`

**2. Structured Logging with Monolog**
- **Why**: Container-friendly (stdout/stderr), JSON format for log aggregation
- **Removed**: 10 debug file writes (`/tmp/debricked-*.txt`) for 12-factor compliance
- **Config**: `config/packages/prod/monolog.yaml`, `config/packages/dev/monolog.yaml`

**3. Async Processing with RabbitMQ**
- **Why**: Long-running scans don't block API responses, horizontal scalability
- **Implementation**: Symfony Messenger with `async` and `scan_status` transports
- **Worker**: Dedicated `messenger-worker` container processes queued jobs

**4. Provider Abstraction Layer**
- **Pattern**: Adapter pattern for multiple scan providers (Debricked, Snyk)
- **Benefits**: Easy to add new providers, consistent interface
- **Location**: `src/Service/Provider/`

**5. Health Checks for Orchestration**
- **Why**: K8s/Docker Swarm compatibility, load balancer integration
- **Endpoints**: `/health/live` (liveness), `/health/ready` (readiness with dependency checks)
- **Config**: `docker-compose.yaml` healthcheck directives

### Testing Strategy
- **Unit Tests**: Service layer business logic (188 tests)
- **Integration Tests**: Database operations, message bus, API endpoints
- **Coverage**: PCOV extension for fast coverage reports
- **CI-Ready**: Exit code 0 on success, non-zero on failure

### Performance Considerations
- **Database Indexing**: Primary keys, foreign keys on high-traffic tables
- **Query Optimization**: Doctrine ORM with query builder, lazy loading
- **Caching**: Framework cache via `cache.yaml` (file system adapter)
- **Connection Pooling**: PHP-FPM with 5 child processes, nginx upstream

## Project Structure
```
├── bin/                    # Console commands (console, phpunit)
├── config/                 # Symfony configuration
│   ├── packages/          # Bundle configs (framework, doctrine, monolog)
│   └── routes/            # Routing configuration
├── docker/                # Docker configuration
│   ├── nginx/             # Nginx config with rate limiting
│   ├── php/               # PHP-FPM Dockerfile
│   └── localstack/        # LocalStack init scripts
├── public/                # Web root (index.php)
├── src/
│   ├── Controller/        # API endpoints
│   ├── Entity/            # Doctrine entities
│   ├── Repository/        # Database repositories
│   ├── Service/           # Business logic
│   │   └── Provider/      # Scan provider adapters
│   ├── MessageHandler/    # Async message handlers
│   └── Kernel.php         # Application kernel
├── tests/                 # PHPUnit tests (188 tests)
├── .env                   # Environment configuration
├── composer.json          # PHP dependencies
└── docker-compose.yaml    # Service orchestration
```

## Common Issues & Solutions

### Port Already in Use
```bash
# Check what's using port 8888
netstat -ano | findstr :8888  # Windows
lsof -i :8888                 # Linux/Mac

# Use different port
# Edit docker-compose.yaml: "9999:80" instead of "8888:80"
```

### Container Won't Start
```bash
# View logs
docker-compose logs nginx php db

# Rebuild containers
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Database Connection Failed
```bash
# Ensure MySQL is ready
docker-compose exec db mysqladmin ping -p

# Check migrations
docker-compose exec php php bin/console doctrine:migrations:status

# Run migrations
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Tests Failing
```bash
# Clear cache
docker-compose exec php php bin/console cache:clear

# Run specific failing test
docker-compose exec php php bin/phpunit tests/Path/To/TestFile.php --verbose

# Check database schema
docker-compose exec php php bin/console doctrine:schema:validate
```
