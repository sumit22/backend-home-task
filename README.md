## Introduction
This a base for Debricked's backend home task. It provides a Symfony skeleton and a Docker environment with a few handy 
services:

- RabbitMQ
- MySQL (available locally at 3307, between Docker services at 3306)
- Mailpit (UI available locally at 8025)
- LocalStack (AWS services emulator, available locally at 4566)
- PHP
- Nginx (available locally at 8888, your API endpoints will accessible through here)

See .env for working credentials for RabbitMQ, MySQL, Mailpit, and LocalStack.

A few notes:
- By default, emails sent through Symfony Mailer will be sent to Mailpit, regardless of recipient.
- LocalStack provides local AWS services (S3, SQS, SNS, DynamoDB, Lambda, Secrets Manager) for development.

## Changes in Runtime

### Mailpit Migration
The application has been updated to use **Mailpit** instead of MailHog for email testing and development:

- **Mailpit** is the actively maintained successor to MailHog
- **SMTP Server**: Available at `localhost:1025` (changed from MailHog's port 1025)
- **Web UI**: Available at `localhost:8025` (same as MailHog)
- **Service Name**: Docker service renamed from `mailhog` to `mailpit` for clarity
- **Benefits**: Better performance, modern UI, API access, and active maintenance

To view emails sent by the application:
1. Start the Docker environment: `docker compose up`
2. Open `http://localhost:8025` in your browser
3. Send test emails through the application - they'll appear in Mailpit's web interface

### Notification System
The application uses Symfony's native Notifier component for sending notifications:

- **Email Notifications**: Sent via Symfony Mailer to Mailpit for development
- **Slack Notifications**: Configured for chat-based notifications
- **Dynamic Channels**: Notification channels are configured per rule action type
- **Repository-Specific Settings**: Future enhancement will allow per-repository notification preferences

## How to use the Docker environment
### Starting the environment
`docker compose up`

### Stopping the environment
`docker compose down`

### Running PHP based commands
You can access the PHP environment's shell by executing `docker compose exec php bash` (make sure the environment is up 
and running before, or the command will fail) in root folder.

We recommend that you always use the PHP container's shell whenever you execute PHP, such as when installing and 
requiring new composer dependencies.

## LocalStack (AWS Services Emulator)

LocalStack emulates AWS services locally. S3 bucket `rule-engine-files` is auto-created on startup.

- **Endpoint**: `http://localhost:4566`
- **Credentials**: `test` / `test`
- **Available**: S3, SQS, SNS, DynamoDB, Lambda, Secrets Manager

File storage uses Flysystem with S3 adapter. Inject `FilesystemOperator` in your services.

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

The application uses PHPUnit for testing with PCOV for code coverage reporting.

### Running Tests

```bash
# Run all tests
docker compose exec php php bin/phpunit

# Run specific test file
docker compose exec php php bin/phpunit tests/Service/ScanServiceTest.php

# Run tests with text coverage report
docker compose exec php php bin/phpunit --coverage-text

# Generate HTML coverage report
docker compose exec php php bin/phpunit --coverage-html coverage
# View the report by opening coverage/index.html in your browser
```

### Test Coverage

Current test coverage includes:
- **ScanService**: 100% method and line coverage (16 tests)
- **ScanController**: 100% method and line coverage (12 tests)
- **RepositoryService**: Comprehensive service layer tests

### Docker Configuration

The PHP Docker image has been customized (see `docker/php/Dockerfile`) to install PCOV extension for code coverage generation. This ensures PCOV persists across container rebuilds.

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
