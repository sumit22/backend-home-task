<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateScanRequest
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'Branch name cannot be longer than {{ limit }} characters'
    )]
    private ?string $branch = null;

    #[Assert\Length(
        max: 64,
        maxMessage: 'Provider code cannot be longer than {{ limit }} characters'
    )]
    private ?string $provider = null;

    #[Assert\Length(
        max: 255,
        maxMessage: 'Requested by cannot be longer than {{ limit }} characters'
    )]
    private ?string $requestedBy = null;

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): self
    {
        $this->branch = $branch;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getRequestedBy(): ?string
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?string $requestedBy): self
    {
        $this->requestedBy = $requestedBy;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'branch' => $this->branch,
            'provider' => $this->provider,
            'requested_by' => $this->requestedBy,
        ];
    }
}
