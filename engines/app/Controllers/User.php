<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

helper(['api_response_helper']);

class User extends BaseController
{
    protected UserModel $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    // GET /api/account/profile
    public function profile()
    {
        $user = $this->authUser();
        if ($user instanceof ResponseInterface) return $user; // error response
        unset($user['password']);
        return api_respond_success($user, 'Profile fetched');
    }

    // PUT /api/account/profile
    public function updateProfile()
    {
        $user = $this->authUser();
        if ($user instanceof ResponseInterface) return $user;

        $json = $this->request->getJSON();
        if (!$json) return api_respond_error('Invalid JSON input', 400);

        $data = [
            'name'  => $json->name  ?? $user['name'],
            'email' => $json->email ?? $user['email'],
            'phone' => $json->phone ?? $user['phone'],
        ];

        // Cek email unik jika berubah
        if ($data['email'] !== $user['email']) {
            $exists = $this->model->where('email', $data['email'])->where('id !=', $user['id'])->first();
            if ($exists) return api_respond_validation_error(['email' => 'Email already used']);
        }

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }

        $this->model->update($user['id'], $data);
        unset($data['email']); // optional hide? keep if needed
        return api_respond_success($data, 'Profile updated');
    }

    // PUT /api/account/change-password
    public function changePassword()
    {
        $user = $this->authUser();
        if ($user instanceof ResponseInterface) return $user;

        $json = $this->request->getJSON();
        if (!$json) return api_respond_error('Invalid JSON input', 400);

        $old = $json->old_password ?? null;
        $new = $json->new_password ?? null;
        if (!$old || !$new) return api_respond_error('Old and new passwords are required', 400);
        if (!password_verify($old, $user['password'])) return api_respond_unauthorized('Invalid old password');
        if (strlen($new) < 6) return api_respond_validation_error(['new_password' => 'Minimum 6 characters']);

        $this->model->update($user['id'], ['password' => password_hash($new, PASSWORD_DEFAULT)]);
        return api_respond_success(null, 'Password changed successfully');
    }

    /**
     * Ambil user ter-autentikasi dari JWT.
     * Return array user atau ResponseInterface bila error.
     */
    private function authUser(): array|ResponseInterface
    {
        $payload = $this->decodeToken();
        if (!$payload) return api_respond_unauthorized('Invalid token');
        $user = $this->model->find($payload->sub ?? 0);
        if (!$user) return api_respond_not_found('User not found');
        return $user;
    }
}
