<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\Config\Services;

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
        // Verifikasi token di sini (misalnya memeriksa apakah token valid)
        return $token === 'Bearer 1cd0bf3e8022fb99af58b642014eb2de'; // Contoh validasi sederhana
    }
}
