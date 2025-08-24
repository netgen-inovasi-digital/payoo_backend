<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use CodeIgniter\HTTP\ResponseInterface;

class Category extends BaseController
{
    protected CategoryModel $model;

    public function __construct()
    {
        $this->model = new CategoryModel();
    }

    // GET /api/kategori
    public function index()
    {
        $categories = $this->model->orderBy('id', 'DESC')->findAll();
        return api_respond_success($categories, 'Category list');
    }

    // GET /api/kategori/{id}
    public function show($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $category = $this->model->find($id);
        if (!$category) {
            return api_respond_not_found('Category not found');
        }
        return api_respond_success($category, 'Category detail');
    }

    // POST /api/kategori
    public function create()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $data = ['name' => trim($json->name ?? '')];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create category');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Category created');
    }

    // PUT /api/kategori/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $existing = $this->model->find($id);
        if (!$existing) {
            return api_respond_not_found('Category not found');
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $data = ['name' => trim($json->name ?? '')];

        // Override validation rule agar unique mengabaikan id saat ini
        $rules = [
            'name' => "required|string|max_length[100]|is_unique[categories.name,id,{$id}]"
        ];
        $this->model->setValidationRules($rules);
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update category');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Category updated');
    }

    // DELETE /api/kategori/{id}
    public function delete($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $existing = $this->model->find($id);
        if (!$existing) {
            return api_respond_not_found('Category not found');
        }
        if (!$this->model->delete($id)) {
            return api_respond_server_error('Failed to delete category');
        }
        return api_respond_success(null, 'Category deleted');
    }
}
