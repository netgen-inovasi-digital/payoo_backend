<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\Config\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BearerAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $token = $request->getHeader('Authorization') ? $request->getHeader('Authorization')->getValue() : null;

        if (!$token || !$this->isValidToken($token)) {
            return Services::response()
                ->setJSON(['message' => 'Unauthorized'])
                ->setStatusCode(401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada tindakan setelah response
    }

    private function isValidToken($token)
    {
        $secret = getenv('JWT_SECRET'); // Ambil dari ENV
        if (strpos($token, 'Bearer ') === 0) {
            $jwt = substr($token, 7);
            try {
                $payload = JWT::decode($jwt, new Key($secret, 'HS256'));
                return !empty($payload);
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }
}
