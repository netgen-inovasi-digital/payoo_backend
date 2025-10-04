<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ShopModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

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
            'photo' => $json->photo ?? $user['photo'],
        ];

        $errors = $this->validateProfileData($data, $user['id']);
        if ($errors) return api_respond_validation_error($errors);

        // Skip model validation because we already validated manually including unique email exception
        if (!$this->model->skipValidation(true)->update($user['id'], $data)) {
            return api_respond_server_error('Failed to update profile');
        }

        $shopId = $this->getShopIdByUserId($user['id']);
        $data['shop_id'] = $shopId;
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
        if (isset($payload->shop_id)) $user['shop_id'] = (int)$payload->shop_id;
        return $user;
    }

    /**
     * Validate profile data.
     * Returns array of errors or empty array/null if valid.
     */
    private function validateProfileData(array $data, int $userId, bool $includeRole = false): ?array
    {
        $rules = [
            'name'  => 'required|max_length[100]',
            'email' => 'required|valid_email|max_length[100]|is_unique[users.email,id,' . $userId . ']',
            'phone' => 'permit_empty|max_length[100]',
            'photo' => 'permit_empty|max_length[255]',
        ];
        if ($includeRole) {
            $rules['role'] = 'required|in_list[owner,employee,user]';
        }

        $validation = Services::validation();
        $validation->setRules($rules);
        if (!$validation->run($data)) {
            return $validation->getErrors();
        }
        return null;
    }

    // ambil shop pertama milik user (jika ada) untuk dimasukkan ke payload
    private function getShopIdByUserId($userId)
    {
        $shop = (new ShopModel())->where('user_id', $userId)->orderBy('id', 'ASC')->first();
        return $shop['id'] ?? null;
    }
}
