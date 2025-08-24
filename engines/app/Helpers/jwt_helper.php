<?php

require_once APPPATH . 'Libraries/PhpJwt/JWT.php';
require_once APPPATH . 'Libraries/PhpJwt/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Encode payload menjadi JWT token.
 *
 * @param array $payload
 * @param string $secret
 * @param int $exp (detik, default 24 jam)
 * @return string
 */
if (! function_exists('jwt_encode')) {
    function jwt_encode(array $payload, string $secret, int $exp = 86400): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $exp;
        return JWT::encode($payload, $secret, 'HS256');
    }
}

/**
 * Decode JWT token menjadi payload.
 *
 * @param string $token
 * @param string $secret
 * @return object|false
 */
if (! function_exists('jwt_decode')) {
    function jwt_decode(string $token, string $secret)
    {
        try {
            return JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Exception $e) {
            return false;
        }
    }
}
