# Test rate limiting on API endpoints
Write-Host "Testing rate limiting..." -ForegroundColor Cyan

# Test general API endpoint (100 req/min = ~1.67 req/sec, burst 20)
Write-Host "`nTesting general API rate limit (100 req/min)..." -ForegroundColor Yellow
for ($i = 1; $i -le 25; $i++) {
    $response = Invoke-WebRequest -Uri "http://localhost/api/repositories" -Method GET -SkipHttpErrorCheck
    $status = $response.StatusCode
    
    if ($status -eq 429) {
        Write-Host "Request $i`: Rate limited! (429)" -ForegroundColor Red
        Write-Host "Response: $($response.Content)" -ForegroundColor Gray
        break
    } else {
        Write-Host "Request $i`: OK ($status)" -ForegroundColor Green
    }
}

Write-Host "`nRate limiting test completed!" -ForegroundColor Cyan
