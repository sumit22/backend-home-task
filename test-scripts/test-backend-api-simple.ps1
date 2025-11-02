# Test Backend Home Task API - Simplified Test
# This script tests the repository scan creation through our Symfony API

$ErrorActionPreference = "Stop"

# Configuration
$API_BASE = "http://localhost:8888"

Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Backend Home Task API Integration Test" -ForegroundColor Blue
Write-Host "========================================`n" -ForegroundColor Blue

# Step 1: Create a Repository
Write-Host "Step 1: Creating a repository..." -ForegroundColor Yellow

$repoBody = @{
    name = "test-repository-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    url = "https://github.com/test-user/test-repo"
    defaultBranch = "main"
    language = "PHP"
} | ConvertTo-Json

try {
    $repoResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories" `
        -Method Post `
        -ContentType "application/json" `
        -Body $repoBody
    
    $REPO_ID = $repoResponse.id
    Write-Host "✓ Repository created!" -ForegroundColor Green
    Write-Host "  Repository ID: $REPO_ID"
    Write-Host "  Repository Name: $($repoResponse.name)`n"
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

# Step 3: Verify scan exists
Write-Host "Step 3: Verifying scan was created..." -ForegroundColor Yellow

try {
    $getScanResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID" `
        -Method Get
    
    Write-Host "✓ Scan verified!" -ForegroundColor Green
    Write-Host "  Scan ID: $($getScanResponse.scan.id)"
    Write-Host "  Status: $($getScanResponse.scan.status)"
    Write-Host "  Branch: $($getScanResponse.scan.branch)`n"
}
catch {
    Write-Host "✗ Failed to retrieve scan!" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)`n"
    exit 1
}

# Step 4: Prepare test files
Write-Host "Step 4: Preparing test files..." -ForegroundColor Yellow

# Use test files from test-files directory
$TEST_FILES = @(
    "test-scripts\test-files\composer.lock",
    "test-scripts\test-files\yarn.lock"
)

# Verify test files exist
$filesToUpload = @()
foreach ($file in $TEST_FILES) {
    if (Test-Path $file) {
        $filesToUpload += $file
        Write-Host "  Found: $file" -ForegroundColor Cyan
    } else {
        Write-Host "  Missing: $file" -ForegroundColor Yellow
    }
}

if ($filesToUpload.Count -eq 0) {
    Write-Host "✗ No test files found!" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Test files ready: $($filesToUpload.Count) file(s)`n"

# Step 5: Upload files using curl (most reliable for multipart)
Write-Host "Step 5: Uploading files to scan..." -ForegroundColor Yellow

# Build curl command with multiple files
$fileParams = ($filesToUpload | ForEach-Object { "-F `"files[]=@$_`"" }) -join " "
$curlCmd = "curl -X POST `"$API_BASE/api/scans/$SCAN_ID/files`" $fileParams -F `"upload_complete=true`" -w `"\n%{http_code}`""
Write-Host "  Command: $curlCmd" -ForegroundColor Cyan

$output = Invoke-Expression $curlCmd 2>&1 | Out-String
$lines = $output -split "`n"
$statusCode = $lines[-2].Trim()
$response = ($lines[0..($lines.Length-3)] -join "`n").Trim()

Write-Host "  HTTP Status: $statusCode"

if ($statusCode -eq "201" -or $statusCode -eq "200") {
    Write-Host "✓ Files uploaded successfully!" -ForegroundColor Green
    if ($response) {
        try {
            $uploadJson = $response | ConvertFrom-Json
            Write-Host "  Files uploaded: $($uploadJson.uploaded.Count)" -ForegroundColor Cyan
            Write-Host "  Status: $($uploadJson.status)`n"
        }
        catch {
            Write-Host "  Response: $response`n"
        }
    }
}
else {
    Write-Host "✗ Upload failed!" -ForegroundColor Red
    Write-Host "  Response: $response"
    Write-Host "  Continuing to check scan status...`n"
}

# Step 6: Check Scan Status
Write-Host "Step 6: Checking scan status..." -ForegroundColor Yellow
Write-Host "  (Waiting for message handler to process...)" -ForegroundColor Cyan

# Poll the scan status
$maxAttempts = 10
$attempt = 0
$completed = $false

while ($attempt -lt $maxAttempts -and -not $completed) {
    Start-Sleep -Seconds 3
    $attempt++
    
    try {
        $statusResponse = Invoke-RestMethod -Uri "$API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID" `
            -Method Get
        
        $status = $statusResponse.scan.status
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
    Write-Host "Check status manually with: GET $API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID"
}

Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "API Test Completed!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Blue
Write-Host "`nCreated Resources:" -ForegroundColor Cyan
Write-Host "  Repository ID: $REPO_ID"
Write-Host "  Scan ID: $SCAN_ID"
Write-Host "`nTo view scan details:" -ForegroundColor Yellow
Write-Host "  GET $API_BASE/api/repositories/$REPO_ID/scans/$SCAN_ID"
Write-Host "`nCleanup (if needed):" -ForegroundColor Yellow
Write-Host "  docker exec -it backend-home-task-mysql-1 mysql -uroot -prootpassword -D home_task_db -e `"DELETE FROM repository_scan WHERE id='$SCAN_ID'`""
Write-Host "  docker exec -it backend-home-task-mysql-1 mysql -uroot -prootpassword -D home_task_db -e `"DELETE FROM repository WHERE id='$REPO_ID'`""
Write-Host ""
