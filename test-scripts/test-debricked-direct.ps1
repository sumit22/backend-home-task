# Direct Debricked API Test - Working Version
# This script tests Debricked API directly using cURL (the method that works!)

$ErrorActionPreference = "Stop"

# Configuration
$DEBRICKED_API_BASE = "https://debricked.com/api"
$REFRESH_TOKEN = "ccc93c89d65d8c667e80849aa0e69fc3a67797ef53f59206"

Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Debricked API Direct Test" -ForegroundColor Blue
Write-Host "========================================`n" -ForegroundColor Blue

# Step 1: Get JWT Token
Write-Host "Step 1: Authenticating with Debricked..." -ForegroundColor Yellow

$refreshBody = @{
    refresh_token = $REFRESH_TOKEN
} | ConvertTo-Json

try {
    $jwtResponse = Invoke-RestMethod -Uri "$DEBRICKED_API_BASE/login_refresh" `
        -Method Post `
        -ContentType "application/json" `
        -Body $refreshBody
    
    $JWT_TOKEN = $jwtResponse.token
    Write-Host "✓ Authentication successful!" -ForegroundColor Green
    Write-Host "JWT Token: $($JWT_TOKEN.Substring(0, [Math]::Min(50, $JWT_TOKEN.Length)))...`n"
}
catch {
    Write-Host "✗ Authentication failed!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 2: Upload dependency file using cURL (the method that works!)
Write-Host "Step 2: Uploading composer.lock using cURL..." -ForegroundColor Yellow

$composerLockPath = Join-Path $PSScriptRoot "..\composer.lock"
if (-not (Test-Path $composerLockPath)) {
    Write-Host "✗ composer.lock not found at: $composerLockPath" -ForegroundColor Red
    exit 1
}

$timestamp = [int][double]::Parse((Get-Date -UFormat %s))
$repositoryName = "test-repository"
$commitName = "test-scan-$timestamp"

# Use cURL with proper multipart form data (this is what works!)
$curlArgs = @(
    "-X", "POST",
    "$DEBRICKED_API_BASE/1.0/open/uploads/dependencies/files",
    "-H", "Authorization: Bearer $JWT_TOKEN",
    "-F", "fileData=@$composerLockPath",
    "-F", "repositoryName=$repositoryName",
    "-F", "commitName=$commitName",
    "-w", "`n%{http_code}",
    "-s"
)

try {
    $uploadOutput = & curl $curlArgs
    $uploadLines = $uploadOutput -split "`n"
    $statusCode = $uploadLines[-1]
    $responseBody = ($uploadLines[0..($uploadLines.Length-2)] -join "`n")
    
    Write-Host "HTTP Status: $statusCode" -ForegroundColor Cyan
    
    if ($statusCode -ne "200") {
        Write-Host "✗ Upload failed!" -ForegroundColor Red
        Write-Host "Response: $responseBody"
        exit 1
    }
    
    $uploadResponse = $responseBody | ConvertFrom-Json
    $CI_UPLOAD_ID = $uploadResponse.ciUploadId
    
    Write-Host "✓ File uploaded successfully!" -ForegroundColor Green
    Write-Host "CI Upload ID: $CI_UPLOAD_ID"
    Write-Host "Upload Programs File ID: $($uploadResponse.uploadProgramsFileId)"
    Write-Host "Remaining Scans: $($uploadResponse.remainingScans)/$($uploadResponse.totalScans)`n"
}
catch {
    Write-Host "✗ Upload failed!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 3: Finalize the scan
Write-Host "Step 3: Finalizing the scan..." -ForegroundColor Yellow

$finalizeBody = @{
    ciUploadId = $CI_UPLOAD_ID
} | ConvertTo-Json

$finalizeHeaders = @{
    "Authorization" = "Bearer $JWT_TOKEN"
    "Content-Type" = "application/json"
}

try {
    # Note: This returns HTTP 204 (No Content) on success
    $finalizeResponse = Invoke-WebRequest -Uri "$DEBRICKED_API_BASE/1.0/open/finishes/dependencies/files/uploads" `
        -Method Post `
        -Headers $finalizeHeaders `
        -Body $finalizeBody `
        -UseBasicParsing
    
    Write-Host "HTTP Status: $($finalizeResponse.StatusCode)" -ForegroundColor Cyan
    
    if ($finalizeResponse.StatusCode -eq 204) {
        Write-Host "✓ Scan finalized! (No content returned - this is normal)`n" -ForegroundColor Green
    }
    else {
        Write-Host "Response: $($finalizeResponse.Content)" -ForegroundColor Cyan
        Write-Host "✓ Scan finalized!`n" -ForegroundColor Green
    }
}
catch {
    Write-Host "✗ Finalization failed!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

# Step 4: Check scan status
Write-Host "Step 4: Checking scan status..." -ForegroundColor Yellow
Write-Host "(Waiting 3 seconds for scan to start...)"
Start-Sleep -Seconds 3

$statusHeaders = @{
    "Authorization" = "Bearer $JWT_TOKEN"
}

try {
    $statusResponse = Invoke-RestMethod -Uri "$DEBRICKED_API_BASE/1.0/open/ci/upload/status?ciUploadId=$CI_UPLOAD_ID" `
        -Method Get `
        -Headers $statusHeaders
    
    Write-Host "`n✓ Scan Status Retrieved:" -ForegroundColor Green
    Write-Host "  Progress: $($statusResponse.progress)%"
    Write-Host "  Vulnerabilities Found: $($statusResponse.vulnerabilitiesFound)"
    Write-Host "  Details Found: $($statusResponse.detailsUrl)"
    
    if ($statusResponse.progress -eq 100) {
        Write-Host "`n✓ Scan completed!" -ForegroundColor Green
        
        if ($statusResponse.vulnerabilitiesFound -gt 0) {
            Write-Host "⚠ Found $($statusResponse.vulnerabilitiesFound) vulnerabilities!" -ForegroundColor Yellow
        } else {
            Write-Host "✓ No vulnerabilities found!" -ForegroundColor Green
        }
    } else {
        Write-Host "`n⏳ Scan is still processing ($($statusResponse.progress)% complete)" -ForegroundColor Yellow
        Write-Host "Check status later with:" -ForegroundColor Cyan
        Write-Host "Invoke-RestMethod -Uri '$DEBRICKED_API_BASE/1.0/open/ci/upload/status?ciUploadId=$CI_UPLOAD_ID' -Headers @{'Authorization'='Bearer <YOUR_JWT_TOKEN>'}"
    }
}
catch {
    Write-Host "✗ Failed to get scan status!" -ForegroundColor Red
    Write-Host $_.Exception.Message
}

Write-Host "`n========================================" -ForegroundColor Blue
Write-Host "Test Completed!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Blue
Write-Host "`nKey Points:" -ForegroundColor Yellow
Write-Host "  - Upload uses cURL with -F (form data) - this is what works!"
Write-Host "  - Finish endpoint returns HTTP 204 (No Content) on success"
Write-Host "  - Status polling may take a few minutes for scan to complete"
Write-Host ""
