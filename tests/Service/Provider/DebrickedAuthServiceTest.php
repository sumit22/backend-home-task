<?php

namespace App\Tests\Service\Provider;

use App\Service\Provider\DebrickedAuthService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DebrickedAuthServiceTest extends TestCase
{
    private $http;
    private $logger;
    private string $username = 'test-user';
    private string $password = 'test-password';
    private string $refreshToken = 'test-refresh-token';
    private string $baseUrl = 'https://debricked.com';

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createService(): DebrickedAuthService
    {
        return new DebrickedAuthService(
            $this->http,
            $this->username,
            $this->password,
            $this->refreshToken,
            $this->baseUrl,
            $this->logger
        );
    }

    public function testGetJwtTokenUsingRefreshToken()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-from-refresh']);

        $this->http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/login_refresh',
                ['body' => ['refresh_token' => 'test-refresh-token']]
            )
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Refreshing Debricked JWT using refresh_token');

        $service = $this->createService();
        $token = $service->getJwtToken();

        $this->assertEquals('jwt-token-from-refresh', $token);
    }

    public function testGetJwtTokenFallsBackToUsernamePasswordWhenRefreshFails()
    {
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willThrowException(new \Exception('Refresh token invalid'));

        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-from-login']);

        $this->http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($refreshResponse, $loginResponse) {
                if (str_contains($url, 'login_refresh')) {
                    return $refreshResponse;
                }
                return $loginResponse;
            });

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Refreshing Debricked JWT using refresh_token');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Refresh token failed, falling back to username/password',
                ['error' => 'Refresh token invalid']
            );

        $service = $this->createService();
        $token = $service->getJwtToken();

        $this->assertEquals('jwt-token-from-login', $token);
    }

    public function testGetJwtTokenFallsBackToUsernamePasswordWhenRefreshThrows()
    {
        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-from-login']);

        $this->http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($loginResponse) {
                if (str_contains($url, 'login_refresh')) {
                    throw new \Exception('Network error');
                }
                return $loginResponse;
            });

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Refresh token failed, falling back to username/password',
                ['error' => 'Network error']
            );

        $service = $this->createService();
        $token = $service->getJwtToken();

        $this->assertEquals('jwt-token-from-login', $token);
    }

    public function testGetJwtTokenUsingUsernamePassword()
    {
        // Make refresh token fail immediately
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willThrowException(new \Exception('Refresh failed'));

        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-from-credentials']);

        $this->http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($refreshResponse, $loginResponse) {
                if (str_contains($url, 'login_refresh')) {
                    return $refreshResponse;
                }
                if (str_contains($url, 'login_check')) {
                    $this->assertEquals([
                        '_username' => 'test-user',
                        '_password' => 'test-password',
                    ], $options['body']);
                    return $loginResponse;
                }
                throw new \Exception('Unexpected URL');
            });

        $service = $this->createService();
        $token = $service->getJwtToken();

        $this->assertEquals('jwt-token-from-credentials', $token);
    }

    public function testGetJwtTokenThrowsExceptionWhenLoginCheckFails()
    {
        $refreshResponse = $this->createMock(ResponseInterface::class);
        $refreshResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn([]);

        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn([]); // No token

        $this->http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url) use ($refreshResponse, $loginResponse) {
                if (str_contains($url, 'login_refresh')) {
                    return $refreshResponse;
                }
                return $loginResponse;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Debricked login_check failed: no token returned');

        $service = $this->createService();
        $service->getJwtToken();
    }

    public function testGetJwtTokenReusesValidToken()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-cached']);

        $this->http->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $service = $this->createService();
        
        // First call should fetch token
        $token1 = $service->getJwtToken();
        $this->assertEquals('jwt-token-cached', $token1);

        // Second call should reuse cached token (no additional HTTP request)
        $token2 = $service->getJwtToken();
        $this->assertEquals('jwt-token-cached', $token2);
        $this->assertSame($token1, $token2);
    }

    public function testGetJwtTokenRefetchesWhenTokenExpired()
    {
        $response1 = $this->createMock(ResponseInterface::class);
        $response1->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-1']);

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-2']);

        $callCount = 0;
        $this->http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function () use (&$callCount, $response1, $response2) {
                $callCount++;
                return $callCount === 1 ? $response1 : $response2;
            });

        $service = $this->createService();
        
        // First call
        $token1 = $service->getJwtToken();
        $this->assertEquals('jwt-token-1', $token1);

        // Clear token to simulate expiry
        $service->clearToken();

        // Second call should fetch new token
        $token2 = $service->getJwtToken();
        $this->assertEquals('jwt-token-2', $token2);
        $this->assertNotEquals($token1, $token2);
    }

    public function testClearToken()
    {
        $response1 = $this->createMock(ResponseInterface::class);
        $response1->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-1']);

        $response2 = $this->createMock(ResponseInterface::class);
        $response2->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token-2']);

        $callCount = 0;
        $this->http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function () use (&$callCount, $response1, $response2) {
                $callCount++;
                return $callCount === 1 ? $response1 : $response2;
            });

        $service = $this->createService();
        
        // Get token
        $token1 = $service->getJwtToken();
        $this->assertEquals('jwt-token-1', $token1);

        // Clear token
        $service->clearToken();

        // Get token again - should make new request
        $token2 = $service->getJwtToken();
        $this->assertEquals('jwt-token-2', $token2);
    }

    public function testGetJwtTokenWithDifferentBaseUrl()
    {
        $customBaseUrl = 'https://custom-debricked.com';
        
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['token' => 'jwt-token']);

        $this->http->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://custom-debricked.com/api/login_refresh',
                $this->anything()
            )
            ->willReturn($response);

        $service = new DebrickedAuthService(
            $this->http,
            $this->username,
            $this->password,
            $this->refreshToken,
            $customBaseUrl,
            $this->logger
        );

        $token = $service->getJwtToken();
        $this->assertEquals('jwt-token', $token);
    }
}
