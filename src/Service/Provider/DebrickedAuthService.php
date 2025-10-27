<?php
namespace App\Service\Provider;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class DebrickedAuthService implements DebrickedAuthServiceInterface
{
    private ?string $jwtToken = null;
    private ?\DateTimeImmutable $jwtExpiry = null;

    public function __construct(
        private HttpClientInterface $http,
        private string $username,
        private string $password,
        private string $refreshToken,
        private string $baseUrl,
        private LoggerInterface $logger
    ) {}

    public function getJwtToken(): string
    {
        // Reuse valid JWT if not expired (allow 60 s skew)
        if ($this->jwtToken && $this->jwtExpiry && $this->jwtExpiry > new \DateTimeImmutable('+60 seconds')) {
            return $this->jwtToken;
        }

        // Prefer refresh_token flow if available
        try {
            $this->logger->debug('Refreshing Debricked JWT using refresh_token');
            $resp = $this->http->request('POST', "{$this->baseUrl}/api/login_refresh", [
                'body' => ['refresh_token' => $this->refreshToken],
            ]);
            $data = $resp->toArray(false);
            if (!empty($data['token'])) {
                $this->jwtToken = $data['token'];
                $this->jwtExpiry = new \DateTimeImmutable('+55 minutes');
                return $this->jwtToken;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Refresh token failed, falling back to username/password', ['error' => $e->getMessage()]);
        }

        // Fallback: username/password
        $resp = $this->http->request('POST', "{$this->baseUrl}/api/login_check", [
            'body' => [
                '_username' => $this->username,
                '_password' => $this->password,
            ],
        ]);
        $data = $resp->toArray(false);
        if (empty($data['token'])) {
            throw new \RuntimeException('Debricked login_check failed: no token returned');
        }
        $this->jwtToken = $data['token'];
        $this->jwtExpiry = new \DateTimeImmutable('+55 minutes');
        return $this->jwtToken;
    }

    public function clearToken(): void
    {
        $this->jwtToken = null;
        $this->jwtExpiry = null;
    }
}
