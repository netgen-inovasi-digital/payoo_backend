<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class JWT extends BaseConfig
{
    public string $secret;
    public string $algo      = 'HS256';
    public int    $ttl;
    public int    $refreshTtl = 604800; // 7 hari untuk refresh token

    public function __construct()
    {
        $this->secret = getenv('JWT_SECRET');
        $this->ttl = getenv('JWT_TTL') ? (int) getenv('JWT_TTL') : 86400; // Default 1 hari
    }
}