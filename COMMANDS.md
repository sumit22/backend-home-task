# Frequently Used Commands - Backend Home Task

## 🔑 Connection Credentials Reference

**Always check these files for actual credentials:**
- **Database**: `.env` → `DATABASE_URL` (mysql://root:docker@db:3306/rule_engine)
- **RabbitMQ**: `.env` → `MESSENGER_TRANSPORT_DSN` (amqp://rabbit:docker@rabbitmq:5672)
- **Docker Compose**: `docker-compose.yaml` → service environment variables

**MySQL credentials are defined in `docker-compose.yaml` under `db` service:**
- `MYSQL_ROOT_PASSWORD`
- `MYSQL_DATABASE`

## MySQL Database Access

### Connect to MySQL (Using Environment Variables)
```bash
# Interactive MySQL shell (reads env vars from container)
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE}'

# Execute single query (using env vars)
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "QUERY HERE"'
```

### Connect to MySQL (Direct - if env vars change, update docker-compose.yaml)
```bash
# Interactive MySQL shell
docker exec -it backend-home-task-db-1 mysql -uroot -pdocker -D rule_engine

# Execute single query
docker exec -it backend-home-task-db-1 mysql -uroot -pdocker -D rule_engine -e "QUERY HERE"
```

### Common Queries (Using Environment Variables)
```bash
# Show all tables
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SHOW TABLES;"'

# Show repositories
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT id, name, url, created_at FROM repository ORDER BY created_at DESC LIMIT 10;"'

# Show scans
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT id, repository_id, status, branch, created_at FROM repository_scan ORDER BY created_at DESC LIMIT 10;"'

# Show notification settings
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT id, repository_id, emails, slack_channels FROM notification_setting;"'

# Show rules
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT id, name, scope, is_active, created_at FROM rule ORDER BY created_at DESC;"'

# Show rule actions
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT ra.id, r.name as rule_name, ra.action_type, ra.config FROM rule_action ra JOIN rule r ON ra.rule_id = r.id;"'

# Count vulnerabilities by scan
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT scan_id, COUNT(*) as vuln_count FROM vulnerability GROUP BY scan_id;"'

# Show recent vulnerabilities
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SELECT id, scan_id, cve_id, severity, package_name FROM vulnerability ORDER BY created_at DESC LIMIT 20;"'
```

### Cleanup Commands (Using Environment Variables)
```bash
# Delete specific scan
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DELETE FROM repository_scan WHERE id=\"SCAN_ID_HERE\";"'

# Delete specific repository (cascade deletes scans)
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DELETE FROM repository WHERE id=\"REPO_ID_HERE\";"'

# Delete all test repositories (created by test script)
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DELETE FROM repository WHERE name LIKE \"test-repository-%\";"'

# Clear all vulnerabilities
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "TRUNCATE TABLE vulnerability;"'
```

### Database Schema Inspection (Using Environment Variables)
```bash
# Describe table structure
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DESCRIBE repository;"'
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DESCRIBE repository_scan;"'
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DESCRIBE notification_setting;"'
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DESCRIBE rule;"'
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "DESCRIBE vulnerability;"'

# Show table creation SQL
docker exec -it backend-home-task-db-1 bash -c 'mysql -uroot -p${MYSQL_ROOT_PASSWORD} -D ${MYSQL_DATABASE} -e "SHOW CREATE TABLE repository\G"'
```

## Unit Testing

### Run All Tests
```bash
docker exec backend-home-task-php-1 php bin/phpunit
```

### Run Specific Test File
```bash
docker exec backend-home-task-php-1 php bin/phpunit tests/Service/RepositoryServiceTest.php
docker exec backend-home-task-php-1 php bin/phpunit tests/Service/ExternalMappingServiceTest.php
docker exec backend-home-task-php-1 php bin/phpunit tests/Service/Provider/DebrickedProviderAdapterGuzzleTest.php
```

### Run Tests by Filter
```bash
# Filter by test class name
docker exec backend-home-task-php-1 php bin/phpunit --filter RepositoryServiceTest

# Filter by test method name
docker exec backend-home-task-php-1 php bin/phpunit --filter testCreateRepository

# Filter by pattern
docker exec backend-home-task-php-1 php bin/phpunit --filter "Service.*Test"
```

### Code Coverage
```bash
# Full coverage report (text)
docker exec backend-home-task-php-1 php bin/phpunit --coverage-text

# Coverage without colors (for PowerShell)
docker exec backend-home-task-php-1 php bin/phpunit --coverage-text --colors=never

# Coverage for specific file
docker exec backend-home-task-php-1 php bin/phpunit --coverage-filter src/Service/RepositoryService.php --coverage-text

# Generate HTML coverage report
docker exec backend-home-task-php-1 php bin/phpunit --coverage-html coverage
```

### Test Options
```bash
# Stop on first failure
docker exec backend-home-task-php-1 php bin/phpunit --stop-on-failure

# Verbose output
docker exec backend-home-task-php-1 php bin/phpunit --verbose

# Show test execution time
docker exec backend-home-task-php-1 php bin/phpunit --testdox
```

## Symfony Console Commands

### Cache Management
```bash
# Clear cache
docker exec backend-home-task-php-1 php bin/console cache:clear

# Warm up cache
docker exec backend-home-task-php-1 php bin/console cache:warmup
```

### Database/Doctrine Commands
```bash
# Run migrations
docker exec backend-home-task-php-1 php bin/console doctrine:migrations:migrate

# Check migration status
docker exec backend-home-task-php-1 php bin/console doctrine:migrations:status

# Load fixtures
docker exec backend-home-task-php-1 php bin/console doctrine:fixtures:load

# Validate schema
docker exec backend-home-task-php-1 php bin/console doctrine:schema:validate
```

### Service Debugging
```bash
# List all services
docker exec backend-home-task-php-1 php bin/console debug:container

# Find services by pattern
docker exec backend-home-task-php-1 php bin/console debug:container --tag=app.provider.adapter

# Show service details
docker exec backend-home-task-php-1 php bin/console debug:container App\Service\RepositoryService

# List all routes
docker exec backend-home-task-php-1 php bin/console debug:router
```

### Messenger (Queue) Commands
```bash
# Consume messages from async queue
docker exec backend-home-task-php-1 php bin/console messenger:consume async -vv

# Consume messages from scan_status queue
docker exec backend-home-task-php-1 php bin/console messenger:consume scan_status -vv

# Consume from all queues
docker exec backend-home-task-php-1 php bin/console messenger:consume async scan_status -vv

# Show failed messages
docker exec backend-home-task-php-1 php bin/console messenger:failed:show
```

### Custom Commands
```bash
# Test email sending
docker exec backend-home-task-php-1 php bin/console app:test-email your-email@example.com
```

## Docker Commands

### Container Management
```bash
# List running containers
docker ps

# List all containers (including stopped)
docker ps -a

# View container logs
docker logs backend-home-task-php-1
docker logs backend-home-task-db-1
docker logs backend-home-task-rabbitmq-1
docker logs backend-home-task-mailpit-1

# Follow logs (real-time)
docker logs -f backend-home-task-php-1

# Restart specific service
docker restart backend-home-task-php-1
docker restart backend-home-task-db-1

# Stop all containers
docker-compose down

# Start all containers
docker-compose up -d

# Rebuild and start
docker-compose up -d --build
```

### Execute Commands in Container
```bash
# Access PHP container shell
docker exec -it backend-home-task-php-1 bash

# Access MySQL container shell
docker exec -it backend-home-task-db-1 bash

# Run command as specific user
docker exec -u www-data backend-home-task-php-1 php bin/console cache:clear
```

## Mailpit (Email Testing)

### Access Mailpit
```bash
# Web UI
http://localhost:8025

# API to check emails
curl http://localhost:8025/api/v1/messages

# Count emails
curl -s http://localhost:8025/api/v1/messages | jq '.total'

# Get latest email
curl -s http://localhost:8025/api/v1/messages | jq '.messages[0]'

# Delete all emails
curl -X DELETE http://localhost:8025/api/v1/messages
```

### PowerShell Version
```powershell
# Check email count
(Invoke-RestMethod http://localhost:8025/api/v1/messages).total

# Get latest email
(Invoke-RestMethod http://localhost:8025/api/v1/messages).messages[0]

# Delete all emails
Invoke-RestMethod -Method DELETE http://localhost:8025/api/v1/messages
```

## RabbitMQ Management

### Access RabbitMQ
```bash
# Management UI
http://localhost:15672
# Username: guest
# Password: guest

# List queues
docker exec backend-home-task-rabbitmq-1 rabbitmqctl list_queues

# List exchanges
docker exec backend-home-task-rabbitmq-1 rabbitmqctl list_exchanges

# Purge specific queue
docker exec backend-home-task-rabbitmq-1 rabbitmqctl purge_queue async
docker exec backend-home-task-rabbitmq-1 rabbitmqctl purge_queue scan_status
```

## S3 (LocalStack) Commands

### Access LocalStack
```bash
# S3 endpoint
http://localhost:4566

# List buckets
docker exec backend-home-task-localstack-1 awslocal s3 ls

# List objects in bucket
docker exec backend-home-task-localstack-1 awslocal s3 ls s3://scans-bucket/

# Download file from S3
docker exec backend-home-task-localstack-1 awslocal s3 cp s3://scans-bucket/path/to/file /tmp/local-file
```

## API Testing

### Manual API Calls
```bash
# Create repository
curl -X POST http://localhost:8888/api/repositories \
  -H "Content-Type: application/json" \
  -d '{"name": "test-repo", "url": "https://github.com/test/repo", "default_branch": "main"}'

# Get repository
curl http://localhost:8888/api/repositories/{repo-id}

# Create scan
curl -X POST http://localhost:8888/api/repositories/{repo-id}/scans \
  -H "Content-Type: application/json" \
  -d '{"branch": "main"}'

# Upload files to scan
curl -X POST http://localhost:8888/api/scans/{scan-id}/files \
  -F "files[]=@/path/to/composer.lock" \
  -F "upload_complete=true"

# Get scan details
curl http://localhost:8888/api/repositories/{repo-id}/scans/{scan-id}
```

### Run E2E Test Script
```powershell
.\dev-resources\test-backend-api-simple.ps1
```

## Composer Commands

### Install Dependencies
```bash
docker exec backend-home-task-php-1 composer install
```

### Update Dependencies
```bash
docker exec backend-home-task-php-1 composer update
```

### Require Package
```bash
docker exec backend-home-task-php-1 composer require vendor/package
```

### Show Installed Packages
```bash
docker exec backend-home-task-php-1 composer show
```

## Troubleshooting

### Check PHP Version
```bash
docker exec backend-home-task-php-1 php -v
```

### Check PHP Extensions
```bash
docker exec backend-home-task-php-1 php -m
```

### Check Symfony Environment
```bash
docker exec backend-home-task-php-1 php bin/console about
```

### View Error Logs
```bash
# PHP logs
docker exec backend-home-task-php-1 tail -f /var/log/php-fpm-error.log

# Nginx logs
docker logs backend-home-task-nginx-1

# Application logs (if configured)
docker exec backend-home-task-php-1 tail -f var/log/*.log
```

### Database Connection Test
```bash
docker exec backend-home-task-php-1 php bin/console doctrine:query:sql "SELECT 1"
```

### Check Service Configuration
```bash
# Show current adapter type
docker exec backend-home-task-php-1 grep "debricked.adapter.type" config/services.yaml
```

## Quick Reference

### Container Names (Check with `docker ps`)
- PHP: `backend-home-task-php-1`
- MySQL/DB: `backend-home-task-db-1` (service name: `db`)
- Nginx: `backend-home-task-nginx-1`
- RabbitMQ: `backend-home-task-rabbitmq-1`
- Mailpit: `backend-home-task-mailpit-1`
- LocalStack: `backend-home-task-localstack-1`
- Messenger Worker: `backend-home-task-messenger-worker-1`

### Ports
- Application: `http://localhost:8888`
- Mailpit Web UI: `http://localhost:8025`
- RabbitMQ Management: `http://localhost:15672`
- MySQL: `localhost:3306`
- LocalStack S3: `http://localhost:4566`

### Default Credentials (See .env file for current values)
- **MySQL**: `root` / `docker` (Database: `rule_engine`)
- **RabbitMQ**: `rabbit` / `docker` (Management UI: `guest` / `guest`)
- **Connection Strings**: Check `.env` file for DATABASE_URL and MESSENGER_TRANSPORT_DSN
