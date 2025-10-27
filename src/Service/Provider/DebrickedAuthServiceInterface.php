<?php
namespace App\Service\Provider;

interface DebrickedAuthServiceInterface
{
    public function getJwtToken(): string;
}
