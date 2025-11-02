# Backend Home Task - Postman Collection

This Postman collection provides a complete API test suite for the Backend Home Task application, mirroring the functionality of the PowerShell test script `test-backend-api-with-notifications.ps1`.

## Collection Created

**Collection Name:** Backend Home Task API Tests  
**Collection ID:** `63172-19b4b9be-645c-46ad-93b2-b2632c2f4729`

## Variables

The collection includes the following pre-configured variables:

| Variable | Default Value | Description |
|----------|---------------|-------------|
| `baseUrl` | `http://localhost:8888` | API base URL |
| `mailpitUrl` | `http://localhost:8025/api/v1` | Mailpit API for email verification |
| `repositoryId` | (empty) | Auto-populated after creating a repository |
| `scanId` | (empty) | Auto-populated after creating a scan |

## Requests Overview

The collection contains the following requests:

### 1. Health Check - Liveness
- **Method:** GET
- **URL:** `{{baseUrl}}/health/live`
- **Description:** K8s liveness probe to check if the application is alive

### 2. Health Check - Readiness
- **Method:** GET
- **URL:** `{{baseUrl}}/health/ready`
- **Description:** K8s readiness probe with database and filesystem checks

### 3. Create Repository
- **Method:** POST
- **URL:** `{{baseUrl}}/api/repositories`
- **Body:**
```json
{
  "name": "test-repository-{{$timestamp}}",
  "url": "https://github.com/test-user/test-repo",
  "defaultBranch": "main",
  "language": "PHP",
  "notification_settings": {
    "emails": ["test@example.com", "dev@example.com"],
    "slack_channels": ["#security-alerts"]
  }
}
```
- **Tests:** Automatically saves `repositoryId` to collection variables
- **Pre-request Script:** None needed (use `{{$timestamp}}` dynamic variable)

### 4. Create Scan
- **Method:** POST
- **URL:** `{{baseUrl}}/api/repositories/{{repositoryId}}/scans`
- **Body:**
```json
{
  "branch": "main",
  "provider": "debricked",
  "requested_by": "postman-test"
}
```
- **Tests:** Automatically saves `scanId` to collection variables

### 5. Upload Files to Scan
- **Method:** POST
- **URL:** `{{baseUrl}}/api/scans/{{scanId}}/files`
- **Body Type:** form-data
- **Fields:**
  - `files[]` (file): Select dependency files from `test-scripts/test-files/` (composer.lock, package-lock.json, etc.)
  - `upload_complete` (text): `true`

### 6. Get Scan Status
- **Method:** GET
- **URL:** `{{baseUrl}}/api/repositories/{{repositoryId}}/scans/{{scanId}}`
- **Description:** Poll this endpoint every few seconds to check scan completion status
- **Valid Statuses:** pending, awaiting_upload, processing, completed, failed

### 7. Get Mailpit Messages
- **Method:** GET
- **URL:** `{{mailpitUrl}}/messages?limit=50`
- **Description:** Retrieve emails from Mailpit to verify scan notifications

### 8. Clear Mailpit Inbox
- **Method:** DELETE
- **URL:** `{{mailpitUrl}}/messages`
- **Description:** Clear all emails before running tests

## Usage Workflow

Follow these steps to test the complete flow:

### Step 1: Clear Mailpit (Optional)
Run **Clear Mailpit Inbox** to start with a clean email inbox.

### Step 2: Create Repository
1. Run **Create Repository**
2. The `repositoryId` variable will be automatically populated
3. Check the response to confirm repository creation with notification settings

### Step 3: Create Scan
1. Run **Create Scan** (uses the `repositoryId` from Step 2)
2. The `scanId` variable will be automatically populated
3. Verify scan status is `pending` or `awaiting_upload`

### Step 4: Upload Files
1. Run **Upload Files to Scan**
2. **Important:** Before sending, attach files:
   - Click on the request
   - Go to Body → form-data
   - For `files[]` key, click "Select Files" and choose dependency files from `test-scripts/test-files/`
   - Look for files like: `composer.lock`, `package-lock.json`, etc.
3. Ensure `upload_complete` is set to `true`

### Step 5: Monitor Scan Progress
1. Run **Get Scan Status** repeatedly (every 3-5 seconds)
2. Watch the status change:
   - `processing` → Scan is running
   - `completed` → Scan finished successfully
   - `failed` → Scan encountered an error

### Step 6: Verify Notifications
1. Run **Get Mailpit Messages**
2. Check the response for scan-related emails
3. Look for subjects containing: "scan", "upload", "vulnerability", "completed"

### Step 7: Verify with Health Checks (Optional)
- Run **Health Check - Liveness** to verify application is running
- Run **Health Check - Readiness** to verify all dependencies are healthy

## Test Scripts to Add

### For "Create Repository" Request

**Tests Tab:**
```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});

pm.test("Response has repository ID", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('id');
    pm.collectionVariables.set('repositoryId', jsonData.id);
    console.log('✓ Repository ID saved:', jsonData.id);
});

pm.test("Repository has notification settings", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.notification_settings).to.have.property('emails');
    pm.expect(jsonData.notification_settings.emails).to.be.an('array');
    pm.expect(jsonData.notification_settings.emails.length).to.be.above(0);
});
```

### For "Create Scan" Request

**Tests Tab:**
```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});

pm.test("Response has scan ID", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('id');
    pm.collectionVariables.set('scanId', jsonData.id);
    console.log('✓ Scan ID saved:', jsonData.id);
});

pm.test("Scan status is valid", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.status).to.be.oneOf(['pending', 'awaiting_upload']);
});
```

### For "Upload Files to Scan" Request

**Tests Tab:**
```javascript
pm.test("Status code is 201", function () {
    pm.response.to.have.status(201);
});

pm.test("Files were uploaded successfully", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('files');
    pm.expect(jsonData.files).to.be.an('array');
    pm.expect(jsonData.files.length).to.be.above(0);
    console.log('✓ Uploaded', jsonData.files.length, 'file(s)');
});

pm.test("Upload is marked as complete", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.status).to.not.equal('awaiting_upload');
});
```

### For "Get Scan Status" Request

**Tests Tab:**
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response contains scan details", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('scan');
    pm.expect(jsonData.scan).to.have.property('id');
    pm.expect(jsonData.scan).to.have.property('status');
});

const jsonData = pm.response.json();
const status = jsonData.scan.status;

pm.test("Scan has a valid status", function () {
    const validStatuses = ['pending', 'awaiting_upload', 'processing', 'completed', 'failed'];
    pm.expect(validStatuses).to.include(status);
});

// Visual feedback in console
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('Current scan status:', status);

if (status === 'processing') {
    console.log('⏳ Scan is still processing. Re-run this request in a few seconds.');
} else if (status === 'completed') {
    console.log('✓ Scan completed successfully!');
    if (jsonData.scan.vulnerabilities) {
        console.log('Vulnerabilities found:', jsonData.scan.vulnerabilities.length);
    }
} else if (status === 'failed') {
    console.log('✗ Scan failed!');
}
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
```

### For "Get Mailpit Messages" Request

**Tests Tab:**
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response contains messages array", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('messages');
    pm.expect(jsonData.messages).to.be.an('array');
});

const jsonData = pm.response.json();
const messages = jsonData.messages;

console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log(`📧 Found ${messages.length} email(s) in Mailpit`);
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

if (messages.length > 0) {
    messages.forEach((msg, index) => {
        console.log(`\nEmail #${index + 1}:`);
        console.log(`  ├─ Subject: ${msg.Subject}`);
        console.log(`  ├─ From: ${msg.From.Address}`);
        console.log(`  ├─ To: ${msg.To[0].Address}`);
        console.log(`  ├─ Time: ${msg.Created}`);
        
        // Check if scan-related
        if (/scan|upload|vulnerability|completed/i.test(msg.Subject)) {
            console.log('  └─ Type: Scan-related notification ✓');
        } else {
            console.log('  └─ Type: Other');
        }
    });
    
    const scanEmails = messages.filter(msg => /scan|upload|vulnerability|completed/i.test(msg.Subject));
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('Notification Summary:');
    console.log(`  Total emails: ${messages.length}`);
    console.log(`  Scan-related: ${scanEmails.length}`);
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    pm.test("At least one scan-related email found", function () {
        pm.expect(scanEmails.length).to.be.above(0);
    });
} else {
    console.log('\n⚠ No emails found in Mailpit!');
    console.log('  This might mean:');
    console.log('  - Notifications are not configured');
    console.log('  - Email delivery is delayed');
    console.log('  - Rules did not trigger notifications');
    console.log('\n  View manually: http://localhost:8025');
}
```

### For "Clear Mailpit Inbox" Request

**Tests Tab:**
```javascript
pm.test("Successfully cleared mailbox", function () {
    pm.expect([200, 204]).to.include(pm.response.code);
});

console.log('✓ Mailpit inbox cleared');
```

## Tips & Best Practices

### 1. Environment Setup
- Ensure Docker containers are running: `docker-compose ps`
- Check services are healthy before starting tests

### 2. File Upload
- The Upload Files request requires manual file selection in Postman
- Look for valid dependency files in `test-scripts/test-files/`:
  - ✓ `composer.lock` - PHP dependencies
  - ✓ `package-lock.json` - Node.js dependencies
  - ✗ Avoid files with "empty" or "unknown" in the name

### 3. Polling Scan Status
- Scans typically take 5-30 seconds to process
- Use Postman's "Send" button repeatedly to poll
- Or use Collection Runner with delays between requests

### 4. Debugging
- Check Console tab in Postman for detailed logs
- View Mailpit UI: http://localhost:8025
- Check API logs: `docker logs -f backend-home-task-php-1`

### 5. Cleanup Between Tests
- Run "Clear Mailpit Inbox" before each test run
- Or manually clean up database:
  ```bash
  docker exec backend-home-task-db-1 mysql -uroot -pdocker home_task_db \
    -e "DELETE FROM repository_scan; DELETE FROM repository;"
  ```

## Comparison with PowerShell Script

This Postman collection provides the same functionality as `test-backend-api-with-notifications.ps1`:

| PowerShell Script Step | Postman Request |
|------------------------|-----------------|
| Step 0: Clear Mailpit inbox | Clear Mailpit Inbox |
| Step 1: Create repository | Create Repository |
| Step 2: Create scan | Create Scan |
| Step 3: Verify scan created | Get Scan Status |
| Step 4: Prepare test files | (Manual file selection) |
| Step 5: Upload files | Upload Files to Scan |
| Step 6: Poll scan status | Get Scan Status (repeat) |
| Step 7: Check Mailpit | Get Mailpit Messages |

## Collection URL

Access your collection in Postman:
```
https://www.postman.com/sumit2209/workspace/my-workspace/collection/63172-19b4b9be-645c-46ad-93b2-b2632c2f4729
```

## Export/Import

To share this collection:
1. Open Postman
2. Right-click collection → Export
3. Choose Collection v2.1 format
4. Save to `postman/Backend-Home-Task-API-Tests.postman_collection.json`

To import:
1. Open Postman
2. Click Import button
3. Select the exported JSON file
