<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProductModel;
use App\Models\ShopModel;
use App\Models\CategoryModel;

class Product extends BaseController
{
    protected ProductModel $model;

    public function __construct()
    {
        $this->model = new ProductModel();
    }

    // GET /api/products
    public function index()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_success([], 'No shop assigned');
        }
        $products = $this->model->where('shop_id', $shopId)->orderBy('id', 'DESC')->findAll();
        return api_respond_success($products, 'Product list');
    }

    // GET /api/products/{id}
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
        $product = $this->model->where('shop_id', $shopId)->find($id);
        if (!$product) {
            return api_respond_not_found('Product not found');
        }
        return api_respond_success($product, 'Product detail');
    }

    // POST /api/products
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
            'shop_id'       => (int) $shopId,
            'category_id'   => (int) ($json->category_id ?? 0),
            'name'          => trim($json->name ?? ''),
            'photo'         => trim($json->photo ?? ''),
            'cost_price'    => $json->cost_price ?? null,
            'selling_price' => $json->selling_price ?? null,
        ];

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }

        // Ensure FK existence
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        if (!(new CategoryModel())->find($data['category_id'])) {
            return api_respond_validation_error(['category_id' => 'Category not found']);
        }

        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create product');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Product created');
    }

    // PUT /api/products/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }

        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        $existing = $this->model->where('shop_id', $shopId)->find($id);
        if (!$existing) {
            return api_respond_not_found('Product not found');
        }

        $data = [
            'shop_id'       => $existing['shop_id'], // tidak boleh diubah lewat update
            'category_id'   => isset($json->category_id) ? (int)$json->category_id : $existing['category_id'],
            'name'          => isset($json->name) ? trim($json->name) : $existing['name'],
            'photo'         => isset($json->photo) ? trim($json->photo) : $existing['photo'],
            'cost_price'    => isset($json->cost_price) ? $json->cost_price : $existing['cost_price'],
            'selling_price' => isset($json->selling_price) ? $json->selling_price : $existing['selling_price'],
        ];

        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!(new ShopModel())->find($data['shop_id'])) {
            return api_respond_validation_error(['shop_id' => 'Shop not found']);
        }
        if (!(new CategoryModel())->find($data['category_id'])) {
            return api_respond_validation_error(['category_id' => 'Category not found']);
        }

        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update product');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Product updated');
    }

    // DELETE /api/products/{id}
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
            return api_respond_not_found('Product not found');
        }
        if (!$this->model->delete($id)) {
            return api_respond_server_error('Failed to delete product');
        }
        return api_respond_success(null, 'Product deleted');
    }
}
