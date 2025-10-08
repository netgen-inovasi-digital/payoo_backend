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

    // GET /api/stocks?type=in|out&product_id=123&page=1&limit=20&search=product_name&date_start=2025-01-01&date_end=2025-12-31
    public function getByShop()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_unauthorized('No shop assigned to user');
        }
        
        // Get pagination parameters
        $page = (int) ($this->request->getGet('page') ?? 1);
        $limit = (int) ($this->request->getGet('limit') ?? 20);
        
        // Get filter parameters
        $filters = [
            'type' => $this->request->getGet('type'), // Filter by transaction type (in/out)
            'product_id' => $this->request->getGet('product_id'), // Filter by specific product ID
            'search' => $this->request->getGet('search'), // Search by product name
            'date_start' => $this->request->getGet('date_start'), // Filter by date range start
            'date_end' => $this->request->getGet('date_end'), // Filter by date range end
        ];
        
        // Validate parameters
        if ($page < 1) {
            $page = 1;
        }
        
        if ($limit < 1 || $limit > 100) {
            $limit = 20; // Default limit 20, max 100 per page
        }
        
        // Validate type filter
        if ($filters['type'] && !in_array($filters['type'], ['in', 'out'])) {
            return api_respond_validation_error(['type' => 'Type must be either "in" or "out"']);
        }
        
        // Validate product_id filter
        if ($filters['product_id'] && !$this->isValidId($filters['product_id'])) {
            return api_respond_validation_error(['product_id' => 'Product ID must be a valid integer']);
        }
        
        // Validate date format
        if ($filters['date_start'] && !$this->isValidDate($filters['date_start'])) {
            return api_respond_validation_error(['date_start' => 'Date start must be in YYYY-MM-DD format']);
        }
        
        if ($filters['date_end'] && !$this->isValidDate($filters['date_end'])) {
            return api_respond_validation_error(['date_end' => 'Date end must be in YYYY-MM-DD format']);
        }
        
        $offset = ($page - 1) * $limit;

        // Get paginated movements and total count with filters
        $result = $this->model->getStockMovementsByShopPaginated($shopId, $filters, $limit, $offset);
        
        return api_respond_success($result['data'], 'Stock movements for shop', 200, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $result['total'],
                'total_pages' => ceil($result['total'] / $limit)
            ],
            'filters' => array_filter($filters) // Show applied filters in response
        ]);
    }

    // GET /api/stocks/products/shop
    public function getProductsByShop()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        
        $shopId = $payload->shop_id ?? null;
        if (!$shopId) {
            return api_respond_unauthorized('No shop assigned to user');
        }

        // Get products with stock information using StockModel
        $items = $this->model->getProductsWithStockByShop($shopId);

        if (empty($items)) {
            return api_respond_success([], 'No products found for this shop');
        }

        return api_respond_success($items, 'Products with stock information');
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    private function isValidDate($date)
    {
        if (!$date) return false;
        
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}