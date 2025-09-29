<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\StockModel;
use App\Models\ProductModel;

helper(['api_response_helper']);

class Stock extends BaseController
{
    protected StockModel $model;

    public function __construct()
    {
        $this->model = new StockModel();
    }

    // GET /api/stocks/{product_id}
    public function show($productId = null)
    {
        if (!$this->isValidId($productId)) {
            return api_respond_validation_error(['product_id' => 'Invalid product id']);
        }
        
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        
        $productModel = new ProductModel();
        $product = $productModel->where('shop_id', $shopId)->find($productId);
        if (!$product) {
            return api_respond_not_found('Product not found');
        }
        
        $rows = $this->model
            ->where('product_id', $productId)
            ->orderBy('date', 'DESC')
            ->findAll();
        
        return api_respond_success($rows, 'Stocks data successfully obtained');
    }

    // POST /api/stocks
    public function create()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $shopId = $payload->shop_id ?? null;
        
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        
        $data = [
            'product_id' => (int) ($json->product_id ?? 0),
            'quantity'   => isset($json->quantity) ? (int) $json->quantity : null,
            'type'       => $json->type ?? null,
            'buy_price'  => $json->buy_price ?? null,
            'notes'      => $json->notes ?? null,
            'date'       => $json->date ?? null,
        ];
        
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        
        // Check if product exists and belongs to user's shop
        $productModel = new ProductModel();
        $product = $productModel->where('shop_id', $shopId)->find($data['product_id']);
        if (!$product) {
            return api_respond_validation_error(['product_id' => 'Product not found in your shop']);
        }
        
        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create stock record');
        }
        
        $created = $this->model->find($this->model->getInsertID());
        
        return api_respond_created($created, 'Stock transaction recorded successfully');
    }

    // GET /api/stocks/shop/{shop_id}?type=in|out
    public function getByShop($shopId = null)
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $tokenShopId = $payload->shop_id ?? null;
        if (!$tokenShopId || $tokenShopId != $shopId) {
            return api_respond_unauthorized('Access denied to this shop');
        }
        
        // Get query parameter for type filter
        $typeFilter = $this->request->getGet('type');
        
        // Validate type parameter if provided
        if ($typeFilter && !in_array($typeFilter, ['in', 'out'])) {
            return api_respond_validation_error(['type' => 'Type must be either "in" or "out"']);
        }
        
        $movements = $this->model->getStockMovementsByShop($shopId, $typeFilter);
        
        return api_respond_success($movements, 'Stock movements for shop');
    }
}
