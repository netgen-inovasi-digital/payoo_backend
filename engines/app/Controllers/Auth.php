<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\ShopModel;
use Config\JWT as JWTConfig;
use Config\Services;

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
                $jwtConfig = new JWTConfig();
                $payload = [
                    'sub' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'shop_id' => $this->getShopIdByUserId($user['id'])
                ];
                try {
                    $token = jwt_encode($payload, $jwtConfig->secret, $jwtConfig->ttl);
                } catch (\Exception $e) {
                    return api_respond_server_error('Failed to generate token');
                }
                $userResponse = $user;
                unset($userResponse['password']);
                return api_respond_created([
                    'token' => $token,
                    'user' => $userResponse
                ], 'User registered successfully');
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

        $email = trim($json->email ?? '');
        $password = $json->password ?? null;
        // Per-field validation: kembalikan error terpisah
        $errors = [];
        if (is_null($email) || $email === '') {
            $errors['email'] = 'Email is required';
        }
        if (is_null($password) || $password === '') {
            $errors['password'] = 'Password is required';
        }
        if (!empty($errors)) {
            return api_respond_validation_error($errors);
        }

        $user = $this->userModel->where('email', $email)->first();
        if (!$user || !password_verify($password, $user['password'])) {
            return api_respond_unauthorized('Invalid credentials');
        }

        $shop_id = $this->getShopIdByUserId($user['id']);
        $jwtConfig = new JWTConfig();
        $payload = [
            'sub' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'shop_id' => $shop_id
        ];

        try {
            $token = jwt_encode($payload, $jwtConfig->secret, $jwtConfig->ttl);
            $userResponse = $user;
            $userResponse['shop_id'] = $shop_id;
            unset($userResponse['password']);
            return api_respond_success([
                'token' => $token,
                'user' => $userResponse
            ], 'Login successful');
        } catch (\Exception $e) {
            return api_respond_server_error('Failed to generate token');
        }
    }

    // ambil shop pertama milik user (jika ada) untuk dimasukkan ke payload
    private function getShopIdByUserId($userId)
    {
        $shop = (new ShopModel())->where('user_id', $userId)->orderBy('id', 'ASC')->first();
        return $shop['id'] ?? null;
    }

    // POST /api/auth/forgot-password
    public function forgotPassword() {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON input', 400);
        }

        $email = trim($json->email ?? '');
        
        // Validasi input email
        if (empty($email)) {
            return api_respond_validation_error(['email' => 'Email is required']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return api_respond_validation_error(['email' => 'Please provide a valid email address']);
        }

        // Cek apakah email terdaftar
        $user = $this->userModel->where('email', $email)->first();
        if (!$user) {
            return api_respond_error('Email not found in our system', 404);
        }

        // Generate OTP (6 digit)
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            // Simpan OTP ke database
            $this->userModel->update($user['id'], ['otp' => $otp]);

            // Kirim email dengan OTP menggunakan template
            $emailService = Services::email();
            $emailService->setTo($email);
            $emailService->setSubject('Reset Password OTP - Payoo App');
            
            // Load email template
            $message = view('emails/forgot_password_otp', [
                'userName' => $user['name'],
                'otpCode' => $otp
            ]);
            
            $emailService->setMessage($message);

            if ($emailService->send()) {
                return api_respond_success(null, 'OTP has been sent to your email address');
            } else {
                return api_respond_server_error('Failed to send OTP email. Please try again later.');
            }

        } catch (\Exception $e) {
            return api_respond_server_error('An error occurred while processing your request');
        }
    }

    // POST /api/auth/forgot-password/verify-otp
    public function verifyOtp() {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON input', 400);
        }

        $email = trim($json->email ?? '');
        $otp = trim($json->otp ?? '');

        // Validasi input
        $errors = [];
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address';
        }

        if (empty($otp)) {
            $errors['otp'] = 'OTP is required';
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $errors['otp'] = 'OTP must be 6 digits';
        }

        if (!empty($errors)) {
            return api_respond_validation_error($errors);
        }

        // Cek apakah email terdaftar
        $user = $this->userModel->where('email', $email)->first();
        if (!$user) {
            return api_respond_error('Email not found in our system', 404);
        }

        // Cek apakah OTP sesuai
        if (empty($user['otp'])) {
            return api_respond_error('No OTP found. Please request a new password reset.', 400);
        }

        if ($user['otp'] !== $otp) {
            return api_respond_error('Invalid OTP code', 400);
        }

        return api_respond_success(null, 'OTP verified successfully. You can now reset your password.');
    }

    // POST /api/auth/reset-password
    public function resetPassword() {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON input', 400);
        }

        $email = trim($json->email ?? '');
        $password = $json->password ?? '';
        $confirm_password = $json->confirm_password ?? '';

        // Validasi input
        $errors = [];
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address';
        }

        if (empty($password)) {
            $errors['password'] = 'Current password is required';
        }

        if (empty($confirm_password)) {
            $errors['confirm_password'] = 'Confirm password is required';
        } elseif (strlen($confirm_password) < 6) {
            $errors['confirm_password'] = 'Confirm password must be at least 6 characters long';
        }

        if (!empty($errors)) {
            return api_respond_validation_error($errors);
        }

        // Cek apakah email terdaftar
        $user = $this->userModel->where('email', $email)->first();
        if (!$user) {
            return api_respond_error('Email not found in our system', 404);
        }

        // Verifikasi password dengan confirm_password
        if ($password !== $confirm_password) {
            return api_respond_error('Password and confirm password do not match', 400);
        }

        // Cek apakah password baru berbeda dari yang lama
        if (password_verify($password, $user['password'])) {
            return api_respond_error('New password must be different from the current password', 400);
        }

        try {
            // Update password and clear OTP
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $this->userModel->update($user['id'], [
                'password' => $hashedPassword,
                'otp' => null
            ]);

            return api_respond_success(null, 'Password has been changed successfully. You can now login with your new password.');

        } catch (\Exception $e) {
            return api_respond_server_error('An error occurred while changing your password');
        }
    }
}
