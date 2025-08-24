<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\Config\Services;
use Config\JWT as JWTConfig;

class BearerAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $token = $request->getHeader('Authorization') ? $request->getHeader('Authorization')->getValue() : null;

        if (!$token || !$this->isValidToken($token)) {
            return Services::response()
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthorized - Invalid or missing token'
                ])
                ->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada tindakan setelah response
    }

    private function isValidToken($token)
    {
        $jwtConfig = new JWTConfig();
        if (!$jwtConfig->secret) {
            return false;
        }
        if (strpos($token, 'Bearer ') === 0) {
            $jwt = substr($token, 7);
            $payload = jwt_decode($jwt, $jwtConfig->secret);
            return !empty($payload) && isset($payload->exp) && $payload->exp > time();
        }
        return false;
    }
}
