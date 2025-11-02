# Vulnerability Scanning Platform

Backend API for security vulnerability scanning built with Symfony 7.3 and Docker.

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)
- Git
- Debricked account credentials (username, password, refresh token)

## Local Setup

### 1. Clone Repository
```bash
git clone <repository-url>
cd backend-home-task
```

### 2. Configure Environment Variables

Copy or verify the `.env` file contains the following required variables:

#### Required for Debricked Integration
```bash
DEBRICKED_USERNAME=your_username
DEBRICKED_PASSWORD=your_password
DEBRICKED_REFRESH_TOKEN=your_refresh_token
DEBRICKED_API_BASE=https://debricked.com/api
```

#### Database Configuration
```bash
DATABASE_URL="mysql://root:docker@db:3306/rule_engine?serverVersion=8.0.40&charset=utf8mb4"
```

#### Message Queue
```bash
MESSENGER_TRANSPORT_DSN=amqp://rabbit:docker@rabbitmq:5672/%2F
```

#### Email Testing
```bash
MAILER_DSN=smtp://mailpit:1025?auth_mode=plain&encryption=null
```

#### AWS Services (LocalStack)
```bash
AWS_ENDPOINT=http://localstack:4566
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_DEFAULT_REGION=us-east-1
AWS_S3_BUCKET=rule-engine-files
AWS_USE_PATH_STYLE_ENDPOINT=true
```

#### Application Settings
```bash
APP_ENV=dev
APP_SECRET=a9e2c9dd900ae81c2d4de07e5288463d
VULNERABILITY_THRESHOLD=3
```

#### Optional: Slack Notifications
```bash
# SLACK_DSN=slack://TOKEN@default?channel=CHANNEL
```

### 3. Start Docker Services
```bash
docker-compose up -d
```

Wait for all services to be healthy (30-60 seconds):
```bash
docker-compose ps
```

### 4. Run Database Migrations
```bash
docker exec backend-home-task-php-1 php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Load Initial Data
```bash
docker exec backend-home-task-php-1 php bin/console doctrine:fixtures:load --no-interaction
```

This loads security scan providers (Debricked, Snyk).

### 6. Verify Installation
```bash
# Check application health
curl http://localhost:8888/health/ready

# Expected response:
# {"status":"ready","checks":{"database":true,"filesystem":true},"timestamp":"..."}

# Run tests
docker exec backend-home-task-php-1 php bin/phpunit

# Expected: OK (188 tests, 696 assertions)
```

## Accessing Services

| Service | URL | Credentials |
|---------|-----|-------------|
| **API** | http://localhost:8888 | - |
| **API Health** | http://localhost:8888/health/ready | - |
| **MySQL** | localhost:3307 | root / docker |
| **RabbitMQ Management** | http://localhost:15672 | rabbit / docker |
| **Mailpit Web UI** | http://localhost:8025 | - |
| **LocalStack** | http://localhost:4566 | test / test |

## Common Operations

### Running Commands

All commands should be executed using the container name:

```bash
# General pattern
docker exec backend-home-task-php-1 php bin/console <command>

# Access container shell
docker exec -it backend-home-task-php-1 bash
```

### Database Operations

```bash
# Run migrations
docker exec backend-home-task-php-1 php bin/console doctrine:migrations:migrate --no-interaction

# Create new migration
docker exec backend-home-task-php-1 php bin/console make:migration

# Validate schema
docker exec backend-home-task-php-1 php bin/console doctrine:schema:validate

# Load fixtures
docker exec backend-home-task-php-1 php bin/console doctrine:fixtures:load --no-interaction
```

### Testing

```bash
# Run all tests
docker exec backend-home-task-php-1 php bin/phpunit

# Run specific test file
docker exec backend-home-task-php-1 php bin/phpunit tests/Service/ScanServiceTest.php

# Generate coverage report
docker exec backend-home-task-php-1 php bin/phpunit --coverage-html coverage
```

### Viewing Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f nginx
docker-compose logs -f backend-home-task-php-1

# Application logs (stdout/stderr)
docker exec backend-home-task-php-1 tail -f /proc/1/fd/1
```

## Troubleshooting

### Services Not Starting

```bash
# Check service status
docker-compose ps

# View logs for errors
docker-compose logs nginx
docker-compose logs backend-home-task-php-1
docker-compose logs backend-home-task-db-1

# Restart all services
docker-compose restart

# Full rebuild
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Database Connection Issues

```bash
# Test database connectivity
docker exec backend-home-task-db-1 mysqladmin ping -p
# Password: docker

# Check if database exists
docker exec backend-home-task-db-1 mysql -u root -pdocker -e "SHOW DATABASES;"

# Verify migrations ran
docker exec backend-home-task-php-1 php bin/console doctrine:migrations:status
```

### Port Conflicts

If ports are already in use, edit `docker-compose.yaml` to change port mappings:
```yaml
ports:
  - "9999:80"  # Change 8888 to 9999
```

### Clear Cache

```bash
# Clear Symfony cache
docker exec backend-home-task-php-1 php bin/console cache:clear

# Clear all cache pools
docker exec backend-home-task-php-1 php bin/console cache:pool:clear cache.global_clearer
```

### Tests Failing

```bash
# Validate database schema
docker exec backend-home-task-php-1 php bin/console doctrine:schema:validate

# Check for migrations
docker exec backend-home-task-php-1 php bin/console doctrine:migrations:status

# Run specific test with verbose output
docker exec backend-home-task-php-1 php bin/phpunit tests/Path/To/Test.php --verbose
```

### Debricked API Issues

Check environment variables are set correctly:
```bash
docker exec backend-home-task-php-1 env | grep DEBRICKED
```

Expected output:
```
DEBRICKED_USERNAME=your_username
DEBRICKED_PASSWORD=your_password
DEBRICKED_REFRESH_TOKEN=your_token
DEBRICKED_API_BASE=https://debricked.com/api
```

### Container Name Not Found

List running containers:
```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
```

Container names may vary. Update commands accordingly.

## API Documentation

### Health Endpoints
```bash
# Liveness probe (is application running?)
curl http://localhost:8888/health/live

# Readiness probe (are dependencies available?)
curl http://localhost:8888/health/ready
```

### Repository Endpoints
```bash
# List repositories
GET /api/repositories

# Create repository
POST /api/repositories
Content-Type: application/json
{
  "name": "my-repo",
  "url": "https://github.com/user/repo"
}

# Get repository
GET /api/repositories/{id}

# Initiate security scan
POST /api/repositories/{id}/scans
```

### Scan Endpoints
```bash
# Upload files for scanning
POST /api/scans/{id}/files

# Get scan status
GET /api/scans/{id}

# Get vulnerabilities
GET /api/scans/{id}/vulnerabilities
```

### Rate Limits

Nginx enforces per-IP rate limiting:

| Endpoint | Rate | Burst | On Exceed |
|----------|------|-------|-----------|
| `/api/*` | 100/min | 20 | 429 JSON error |
| `/api/repositories/{id}/scans` | 20/min | 10 | 429 JSON error |
| `/api/scans/{id}/files` | 10/min | 5 | 429 JSON error |

## Technical Details

### Stack
- **Framework:** Symfony 7.3
- **Database:** MySQL 8.0
- **Queue:** RabbitMQ 3.9
- **Web Server:** Nginx (with rate limiting)
- **Email:** Mailpit (development)
- **AWS Emulator:** LocalStack
- **Testing:** PHPUnit 9.5 with PCOV

### Entities
- **Upload** - File upload metadata (repository, commit, timestamp)
- **ScanResult** - Scan execution results
- **Vulnerability** - Security vulnerabilities found

### Async Processing
- Long-running scans processed via RabbitMQ
- Dedicated `messenger-worker` container
- Two transports: `async` (general), `scan_status` (polling)

### Notifications
- Email via Symfony Mailer (Mailpit in dev)
- Slack webhooks (optional)
- Configurable per rule action

### Logging
- Structured JSON logs to stdout/stderr (production)
- Monolog handlers for different environments
- No file-based logging (12-factor compliance)



## Architecture

**Principles:** 12-Factor App, environment-based config, stateless processes, structured logging

**Key Decisions:**
- Nginx rate limiting for performance
- RabbitMQ async processing with dedicated worker
- Stdout/stderr logging (container-friendly)
- Provider adapter pattern for multiple scan services
- K8s-compatible health checks

## Project Structure
```
src/
├── Controller/         # API endpoints
├── Entity/            # Database models (Upload, ScanResult, Vulnerability)
├── Service/           # Business logic
│   └── Provider/      # Scan provider adapters (Debricked, Snyk)
├── MessageHandler/    # Async job handlers
└── Repository/        # Database repositories

config/
├── packages/          # Bundle configuration
└── routes/            # Route definitions

docker/
├── nginx/             # Web server + rate limiting
├── php/               # PHP-FPM container
└── localstack/        # AWS emulator setup

tests/                 # PHPUnit tests (188 tests, 696 assertions)
```

