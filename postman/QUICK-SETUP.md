# Postman Collection Quick Setup Guide

## Quick Configuration for Each Request

Copy and paste these configurations into your Postman requests:

---

### 1. Health Check - Liveness
```
Method: GET
URL: {{baseUrl}}/health/live
```

---

### 2. Health Check - Readiness
```
Method: GET
URL: {{baseUrl}}/health/ready
```

---

### 3. Create Repository

**URL Configuration:**
```
Method: POST
URL: {{baseUrl}}/api/repositories
Headers:
  Content-Type: application/json
```

**Body (raw JSON):**
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

**Tests Script:**
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
});
```

---

### 4. Create Scan

**URL Configuration:**
```
Method: POST
URL: {{baseUrl}}/api/repositories/{{repositoryId}}/scans
Headers:
  Content-Type: application/json
```

**Body (raw JSON):**
```json
{
  "branch": "main",
  "provider": "debricked",
  "requested_by": "postman-test"
}
```

**Tests Script:**
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

---

### 5. Upload Files to Scan

**URL Configuration:**
```
Method: POST
URL: {{baseUrl}}/api/scans/{{scanId}}/files
Body: form-data
```

**Body (form-data):**
| Key | Type | Value |
|-----|------|-------|
| `files[]` | File | (Select files from test-scripts/test-files/) |
| `upload_complete` | Text | `true` |

**Tests Script:**
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
```

---

### 6. Get Scan Status

**URL Configuration:**
```
Method: GET
URL: {{baseUrl}}/api/repositories/{{repositoryId}}/scans/{{scanId}}
```

**Tests Script:**
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

const jsonData = pm.response.json();
const status = jsonData.scan.status;

pm.test("Scan has a valid status", function () {
    const validStatuses = ['pending', 'awaiting_upload', 'processing', 'completed', 'failed'];
    pm.expect(validStatuses).to.include(status);
});

console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('Current scan status:', status);

if (status === 'processing') {
    console.log('⏳ Scan is still processing. Re-run in a few seconds.');
} else if (status === 'completed') {
    console.log('✓ Scan completed successfully!');
} else if (status === 'failed') {
    console.log('✗ Scan failed!');
}
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
```

---

### 7. Get Mailpit Messages

**URL Configuration:**
```
Method: GET
URL: {{mailpitUrl}}/messages?limit=50
```

**Tests Script:**
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

const jsonData = pm.response.json();
const messages = jsonData.messages;

console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log(`📧 Found ${messages.length} email(s) in Mailpit`);
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

if (messages.length > 0) {
    messages.forEach((msg, index) => {
        console.log(`\nEmail #${index + 1}:`);
        console.log(`  Subject: ${msg.Subject}`);
        console.log(`  From: ${msg.From.Address}`);
        console.log(`  To: ${msg.To[0].Address}`);
    });
    
    const scanEmails = messages.filter(msg => /scan|upload|vulnerability|completed/i.test(msg.Subject));
    console.log(`\nScan-related emails: ${scanEmails.length}`);
    
    pm.test("At least one scan-related email found", function () {
        pm.expect(scanEmails.length).to.be.above(0);
    });
} else {
    console.log('\n⚠ No emails found. Check http://localhost:8025');
}
```

---

### 8. Clear Mailpit Inbox

**URL Configuration:**
```
Method: DELETE
URL: {{mailpitUrl}}/messages
```

**Tests Script:**
```javascript
pm.test("Successfully cleared mailbox", function () {
    pm.expect([200, 204]).to.include(pm.response.code);
});

console.log('✓ Mailpit inbox cleared');
```

---

## Setup Checklist

- [ ] Collection variables configured:
  - `baseUrl` = `http://localhost:8888`
  - `mailpitUrl` = `http://localhost:8025/api/v1`
  - `repositoryId` = (empty, auto-filled)
  - `scanId` = (empty, auto-filled)

- [ ] All requests configured with URLs and methods
- [ ] Request bodies added (JSON for POST requests)
- [ ] Test scripts added to all requests
- [ ] File upload request configured with form-data

## Testing Workflow

1. **Clear Mailpit Inbox** → Delete existing emails
2. **Create Repository** → Get repositoryId
3. **Create Scan** → Get scanId
4. **Upload Files to Scan** → Attach dependency files
5. **Get Scan Status** → Poll until completed (3-5 second intervals)
6. **Get Mailpit Messages** → Verify notifications sent

## Pro Tips

- Use Postman's **Collection Runner** to automate the workflow
- Add delays between requests in Runner (5000ms for scan polling)
- Use **Environments** to switch between dev/staging/prod
- Export collection regularly for backup
