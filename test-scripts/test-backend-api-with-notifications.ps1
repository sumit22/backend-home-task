# Test Backend Home Task API with Email/Notification Verification
# This script tests the repository scan creation AND verifies notifications are sent

$ErrorActionPreference = "Stop"

# Configuration
$API_BASE = "http://localhost:8888"
$MAILPIT_API = "http://localhost:8025/api/v1"

Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Backend Home Task API + Notifications Test" -ForegroundColor Blue
Write-Host "========================================`n" -ForegroundColor Blue

# Step 0: Clear Mailpit inbox before test
Write-Host "Step 0: Clearing Mailpit inbox..." -ForegroundColor Yellow
try {
    Invoke-RestMethod -Uri "$MAILPIT_API/messages" -Method Delete -ErrorAction SilentlyContinue | Out-Null
    Write-Host "✓ Mailpit inbox cleared`n" -ForegroundColor Green
}
catch {
    Write-Host "⚠ Could not clear Mailpit (not critical)`n" -ForegroundColor Yellow
}

# Step 1: Create a Repository with notification settings
Write-Host "Step 1: Creating a repository with notification settings..." -ForegroundColor Yellow

$repoBody = @{
    name = "test-repository-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    url = "https://github.com/test-user/test-repo"
    defaultBranch = "main"
    language = "PHP"
    notification_settings = @{
        emails = @("test@example.com", "dev@example.com")
        slack_channels = @("#security-alerts")
    }
} | ConvertTo-Json

try {
    $repoResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories" `
        -Method Post `
        -ContentType "application/json" `
        -Body $repoBody
    
    $REPO_ID = $repoResponse.id
    Write-Host "✓ Repository created with notification settings!" -ForegroundColor Green
    Write-Host "  Repository ID: $REPO_ID"
    Write-Host "  Repository Name: $($repoResponse.name)"
    Write-Host "  Notification Emails: test@example.com, dev@example.com`n"
}
catch {
    Write-Host "Failed to create repository!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 2: Create a Scan
Write-Host "Step 2: Creating a scan..." -ForegroundColor Yellow

$scanBody = @{
    branch = "main"
    provider = "debricked"
    requested_by = "api-test"
} | ConvertTo-Json

try {
    $scanResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories/$REPO_ID/scans" `
        -Method Post `
        -ContentType "application/json" `
        -Body $scanBody
    
    $SCAN_ID = $scanResponse.id
    Write-Host "✓ Scan created!" -ForegroundColor Green
    Write-Host "  Scan ID: $SCAN_ID"
    Write-Host "  Status: $($scanResponse.status)`n"
}
catch {
    Write-Host "Failed to create scan!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 3: Verify scan was created
Write-Host "Step 3: Verifying scan was created..." -ForegroundColor Yellow

try {
    $verifyResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID" `
        -Method Get
    
    Write-Host "✓ Scan verified!" -ForegroundColor Green
    Write-Host "  Scan ID: $($verifyResponse.scan.id)"
    Write-Host "  Status: $($verifyResponse.scan.status)"
    Write-Host "  Branch: $($verifyResponse.scan.branch)`n"
}
catch {
    Write-Host "Failed to verify scan!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 4: Prepare and upload test files
Write-Host "Step 4: Preparing test files..." -ForegroundColor Yellow

$testFilesPath = "test-scripts\test-files"
# Only use valid dependency files
$testFiles = Get-ChildItem -Path $testFilesPath -File | Where-Object { 
    $_.Name -match '\.(lock|json)$' -and $_.Name -notmatch 'empty|unknown'
} | Select-Object -First 2

if ($testFiles.Count -eq 0) {
    Write-Host "No test files found in $testFilesPath!" -ForegroundColor Red
    exit 1
}

foreach ($file in $testFiles) {
    Write-Host "  Found: $($file.FullName)"
}
Write-Host "✓ Test files ready: $($testFiles.Count) file(s)`n" -ForegroundColor Green

# Step 5: Upload files
Write-Host "Step 5: Uploading files to scan..." -ForegroundColor Yellow

$curlArgs = @(
    "-X", "POST",
    "`"$API_BASE/api/scans/$SCAN_ID/files`""
)

foreach ($file in $testFiles) {
    $curlArgs += "-F"
    $curlArgs += "`"files[]=@$($file.FullName)`""
}

$curlArgs += "-F"
$curlArgs += "`"upload_complete=true`""
$curlArgs += "-w"
$curlArgs += "`"`n%{http_code}`""

$curlCommand = "curl " + ($curlArgs -join " ")
Write-Host "  Command: $curlCommand" -ForegroundColor Cyan

$output = Invoke-Expression $curlCommand
$lines = $output -split "`n"
$httpStatus = $lines[-1]

Write-Host "  HTTP Status: $httpStatus" -ForegroundColor Cyan

if ($httpStatus -eq "201") {
    Write-Host "✓ Files uploaded successfully!" -ForegroundColor Green
    
    $uploadResponse = $lines[0..($lines.Count - 2)] -join "`n" | ConvertFrom-Json
    Write-Host "  Files uploaded: $($uploadResponse.files.Count)"
    Write-Host "  Status: $($uploadResponse.status)`n"
}
else {
    Write-Host "✗ File upload failed with status: $httpStatus" -ForegroundColor Red
    exit 1
}

# Step 6: Wait for scan to complete
Write-Host "Step 6: Checking scan status..." -ForegroundColor Yellow
Write-Host "  (Waiting for message handler to process...)" -ForegroundColor Cyan

$maxAttempts = 10
$attempt = 0
$completed = $false
$finalStatus = ""

while ($attempt -lt $maxAttempts -and -not $completed) {
    Start-Sleep -Seconds 3
    $attempt++
    
    try {
        $statusResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID" `
            -Method Get
        
        $status = $statusResponse.scan.status
        $finalStatus = $status
        Write-Host "  Attempt $attempt/$maxAttempts - Status: $status" -ForegroundColor Cyan
        
        if ($status -eq "completed" -or $status -eq "failed") {
            $completed = $true
            
            Write-Host "`n✓ Scan processing finished!" -ForegroundColor Green
            Write-Host "`nFinal Scan Details:" -ForegroundColor Blue
            $statusResponse | ConvertTo-Json -Depth 5
        }
    }
    catch {
        Write-Host "  Attempt $attempt - Status check failed: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

if (-not $completed) {
    Write-Host "`n⏳ Scan is still processing after $maxAttempts attempts." -ForegroundColor Yellow
}

# Step 7: Check for emails in Mailpit
Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Step 7: Verifying Email Notifications" -ForegroundColor Yellow
Write-Host "========================================`n" -ForegroundColor Blue

Start-Sleep -Seconds 2  # Give email system time to deliver

try {
    $mailpitResponse = Invoke-RestMethod -Uri "$MAILPIT_API/messages?limit=50" -Method Get
    $messages = $mailpitResponse.messages
    
    if ($messages.Count -gt 0) {
        Write-Host "✓ Found $($messages.Count) email(s) in Mailpit!" -ForegroundColor Green
        Write-Host "`nEmail Details:" -ForegroundColor Cyan
        
        $emailCount = 0
        foreach ($msg in $messages) {
            $emailCount++
            Write-Host "`n  Email #$emailCount" -ForegroundColor White
            Write-Host "  ├─ Subject: $($msg.Subject)" -ForegroundColor Gray
            Write-Host "  ├─ From: $($msg.From.Address)" -ForegroundColor Gray
            Write-Host "  ├─ To: $($msg.To[0].Address)" -ForegroundColor Gray
            Write-Host "  ├─ Time: $($msg.Created)" -ForegroundColor Gray
            
            # Check if email is related to our scan
            if ($msg.Subject -match "scan|upload|vulnerability|completed") {
                Write-Host "  └─ Type: Scan-related notification ✓" -ForegroundColor Green
            }
            else {
                Write-Host "  └─ Type: Other" -ForegroundColor Gray
            }
        }
        
        # Summary
        $scanEmails = $messages | Where-Object { $_.Subject -match "scan|upload|vulnerability|completed" }
        Write-Host "`nNotification Summary:" -ForegroundColor Cyan
        Write-Host "  Total emails: $($messages.Count)"
        Write-Host "  Scan-related: $($scanEmails.Count)" -ForegroundColor $(if ($scanEmails.Count -gt 0) { "Green" } else { "Yellow" })
        
        # Open Mailpit UI
        Write-Host "`n📧 View emails in Mailpit: http://localhost:8025" -ForegroundColor Cyan
        
    }
    else {
        Write-Host "⚠ No emails found in Mailpit!" -ForegroundColor Yellow
        Write-Host "  This might mean:" -ForegroundColor Gray
        Write-Host "  - Notifications are not configured" -ForegroundColor Gray
        Write-Host "  - Email delivery is delayed" -ForegroundColor Gray
        Write-Host "  - Rules did not trigger notifications" -ForegroundColor Gray
        Write-Host "`n  Check Mailpit manually: http://localhost:8025" -ForegroundColor Cyan
    }
}
catch {
    Write-Host "⚠ Could not check Mailpit!" -ForegroundColor Yellow
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Gray
    Write-Host "  View manually: http://localhost:8025" -ForegroundColor Cyan
}

# Final Summary
Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Test Summary" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Blue

Write-Host "`n✓ Repository: Created with notification settings" -ForegroundColor Green
Write-Host "✓ Scan: Created and files uploaded" -ForegroundColor Green
Write-Host "✓ Processing: $finalStatus" -ForegroundColor $(if ($finalStatus -eq "completed") { "Green" } else { "Yellow" })
Write-Host "$(if ($messages.Count -gt 0) { '✓' } else { '⚠' }) Notifications: $($messages.Count) email(s) sent" -ForegroundColor $(if ($messages.Count -gt 0) { "Green" } else { "Yellow" })

Write-Host "`nCreated Resources:" -ForegroundColor Cyan
Write-Host "  Repository ID: $REPO_ID"
Write-Host "  Scan ID: $SCAN_ID"

Write-Host "`nUseful Links:" -ForegroundColor Yellow
Write-Host "  Scan Details: GET $API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID"
Write-Host "  Mailpit UI: http://localhost:8025"

Write-Host "`nCleanup (if needed):" -ForegroundColor Yellow
Write-Host "  docker exec -it backend-home-task-mysql-1 mysql -uroot -prootpassword -D home_task_db -e `"DELETE FROM repository_scan WHERE id='$SCAN_ID'`""
Write-Host "  docker exec -it backend-home-task-mysql-1 mysql -uroot -prootpassword -D home_task_db -e `"DELETE FROM repository WHERE id='$REPO_ID'`""
Write-Host ""
