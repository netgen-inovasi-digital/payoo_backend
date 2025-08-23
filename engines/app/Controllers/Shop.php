<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ShopModel;
use App\Models\UserModel;

helper(['api_response_helper', 'jwt_helper']);

class Shop extends BaseController
{
    protected ShopModel $model;

    public function __construct()
    {
        $this->model = new ShopModel();
    }

    // GET /api/shops
    public function index()
    {
        $shops = $this->model->orderBy('id', 'DESC')->findAll();
        return api_respond_success($shops, 'Shop list');
    }

    // GET /api/shops/{id}
    public function show($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $shop = $this->model->find($id);
        if (!$shop) {
            return api_respond_not_found('Shop not found');
        }
        return api_respond_success($shop, 'Shop detail');
    }

    // POST /api/shops
    public function create()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }

        $data = [
            'user_id' => (int) ($json->user_id ?? $payload->sub ?? 0), // allow explicit user_id but fallback to token owner
            'name'    => trim($json->name ?? ''),
            'email'   => trim($json->email ?? ''),
            'address' => trim($json->address ?? ''),
            'phone'   => trim($json->phone ?? ''),
        ];

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }

        // Optional: ensure user exists
        if (!(new UserModel())->find($data['user_id'])) {
            return api_respond_validation_error(['user_id' => 'User not found']);
        }

        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create shop');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Shop created');
    }

    // PUT /api/shops/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $existing = $this->model->find($id);
        if (!$existing) {
            return api_respond_not_found('Shop not found');
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }

        $data = [
            'name'    => isset($json->name) ? trim($json->name) : $existing['name'],
            'email'   => isset($json->email) ? trim($json->email) : $existing['email'],
            'address' => isset($json->address) ? trim($json->address) : $existing['address'],
            'phone'   => isset($json->phone) ? trim($json->phone) : $existing['phone'],
        ];

        // Preserve user_id (cannot change via update here)
        $data['user_id'] = $existing['user_id'];

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }

        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update shop');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Shop updated');
    }

    // DELETE /api/shops/{id}
    public function delete($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $existing = $this->model->find($id);
        if (!$existing) {
            return api_respond_not_found('Shop not found');
        }
        if (!$this->model->delete($id)) {
            return api_respond_server_error('Failed to delete shop');
        }
        return api_respond_success(null, 'Shop deleted');
    }
}
