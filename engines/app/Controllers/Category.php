<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\ShopModel;
use CodeIgniter\HTTP\ResponseInterface;

class Category extends BaseController
{
    protected CategoryModel $model;

    public function __construct()
    {
        $this->model = new CategoryModel();
    }

    // GET /api/categories
    public function index()
    {
        // Ambil shop_id dari token JWT
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_success([], 'No shop assigned');
        }

        $categories = $this->model->where('shop_id', $shopId)->orderBy('id', 'DESC')->findAll();
        return api_respond_success($categories, 'Category list');
    }

    // GET /api/categories/{id}
    public function show($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        $category = $this->model->where('shop_id', $shopId)->find($id);
        if (!$category) {
            return api_respond_not_found('Category not found');
        }
        return api_respond_success($category, 'Category detail');
    }

    // POST /api/categories
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
        
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_validation_error(['shop_id' => 'No shop in token']);
        }
        
        $data = [
            'shop_id' => (int) $shopId,
            'name' => trim($json->name ?? '')
        ];
        
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        
        // Validate shop exists
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        
        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create category');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Category created');
    }

    // PUT /api/categories/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Category not found');
        }
        
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        
        $data = [
            'shop_id' => $existing['shop_id'], // tidak boleh diubah
            'name' => trim($json->name ?? '')
        ];

        // Override validation rule agar unique mengabaikan id saat ini dan scope ke shop_id
        $rules = [
            'name' => "required|string|max_length[100]|is_unique[categories.name,id,{$id}]",
            'shop_id' => 'required|integer'
        ];
        $this->model->setValidationRules($rules);
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        
        // Validate shop exists
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        
        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update category');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Category updated');
    }

    // DELETE /api/categories/{id}
    public function delete($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Category not found');
        }
        
        if (!$this->model->delete($id)) {
            return api_respond_server_error('Failed to delete category');
        }
        return api_respond_success(null, 'Category deleted');
    }
}
