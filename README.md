## Introduction
This a base for Debricked's backend home task. It provides a Symfony skeleton and a Docker environment with a few handy 
services:

- RabbitMQ
- MySQL (available locally at 3307, between Docker services at 3306)
- MailHog (UI available locally at 8025)
- LocalStack (AWS services emulator, available locally at 4566)
- PHP
- Nginx (available locally at 8888, your API endpoints will accessible through here)

See .env for working credentials for RabbitMQ, MySQL, MailHog, and LocalStack.

A few notes:
- By default, emails sent through Symfony Mailer will be sent to MailHog, regardless of recipient.
- LocalStack provides local AWS services (S3, SQS, SNS, DynamoDB, Lambda, Secrets Manager) for development.

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
