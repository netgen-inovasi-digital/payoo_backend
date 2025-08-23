<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use Config\JWT as JWTConfig;

class Auth extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // POST /api/auth/register
    public function register()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON input', 400);
        }

        $data = [
            'name' => $json->name ?? null,
            'email' => $json->email ?? null,
            'phone' => $json->phone ?? null,
            'password' => $json->password ?? null,
            'role' => $json->role ?? 'user'
        ];

        if (!$this->userModel->validate($data)) {
            return api_respond_validation_error($this->userModel->errors());
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $userId = $this->userModel->insert($data);
            if ($userId) {
                $user = $this->userModel->find($userId);
                unset($user['password']);
                return api_respond_created($user, 'User registered successfully');
            } else {
                return api_respond_error('Failed to register user', 500);
            }
        } catch (\Exception $e) {
            return api_respond_server_error('Registration failed: ' . $e->getMessage());
        }
    }

    // POST /api/auth/login
    public function login()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON input', 400);
        }

        $email = $json->email ?? null;
        $password = $json->password ?? null;
        if (!$email || !$password) {
            return api_respond_error('Email and password are required', 400);
        }

        $user = $this->userModel->where('email', $email)->first();
        if (!$user || !password_verify($password, $user['password'])) {
            return api_respond_unauthorized('Invalid credentials');
        }

        $jwtConfig = new JWTConfig();
        $payload = [
            'sub' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        try {
            $token = jwt_encode($payload, $jwtConfig->secret, $jwtConfig->ttl);
            $userResponse = $user;
            unset($userResponse['password']);
            return api_respond_success([
                'token' => $token,
                'user' => $userResponse
            ], 'Login successful');
        } catch (\Exception $e) {
            return api_respond_server_error('Failed to generate token');
        }
    }
}
