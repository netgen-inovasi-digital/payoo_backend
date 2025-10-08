<?php

namespace App\Models;

use CodeIgniter\Model;

class StockModel extends Model
{
    protected $table            = 'stocks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id',
        'quantity',
        'type',
        'buy_price',
        'notes',
        'date',
        'created_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'         => 'integer',
        'product_id' => 'integer',
        'quantity'   => 'integer',
        'buy_price'  => '?float',
    ];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules = [
        'product_id' => 'required|integer',
        'quantity'   => 'required|integer',
        'type'       => 'required|in_list[in,out]',
        'buy_price'  => 'permit_empty|decimal',
        'notes'      => 'permit_empty|string',
        'date'       => 'permit_empty|valid_date',
    ];
    protected $validationMessages = [
        'type' => [
            'in_list' => 'Type must be either in or out'
        ],
        'date' => [
            'valid_date' => 'Date must be a valid datetime'
        ]
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Get current stock for a product
     */
    public function getCurrentStock($productId)
    {
        $result = $this->selectSum('quantity')
                      ->where('product_id', $productId)
                      ->first();
        
        return $result['quantity'] ?? 0;
    }

    
    /**
     * Get stock movements by shop
     */
    public function getStockMovementsByShop($shopId, $typeFilter = null)
    {
        $builder = $this->select('stocks.*, products.name as product_name, products.type as product_type')
                       ->join('products', 'products.id = stocks.product_id')
                       ->where('products.shop_id', $shopId);
        
        // Apply type filter if provided
        if ($typeFilter) {
            $builder->where('stocks.type', $typeFilter);
        }
        
        return $builder->orderBy('stocks.date', 'DESC')
                      ->findAll();
    }

    /**
     * Get paginated stock movements by shop
     */
    public function getStockMovementsByShopPaginated($shopId, $typeFilter = null, $limit = 20, $offset = 0)
    {
        // Build base query for data
        $builder = $this->select('stocks.*, products.name as product_name, products.type as product_type')
                       ->join('products', 'products.id = stocks.product_id')
                       ->where('products.shop_id', $shopId);
        
        // Apply type filter if provided
        if ($typeFilter) {
            $builder->where('stocks.type', $typeFilter);
        }
        
        // Get total count for pagination
        $totalBuilder = clone $builder;
        $total = $totalBuilder->countAllResults();
        
        // Get paginated data
        $data = $builder->orderBy('stocks.date', 'DESC')
                       ->limit($limit, $offset)
                       ->findAll();
        
        return [
            'data' => $data,
            'total' => $total
        ];
    }

    /**
     * Add stock in
     */
    public function addStockIn($productId, $quantity, $buyPrice = null, $notes = null)
    {
        $data = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'type'       => 'in',
            'buy_price'  => $buyPrice,
            'notes'      => $notes,
            'date'       => date('Y-m-d H:i:s')
        ];

        return $this->insert($data);
    }

    /**
     * Add stock out
     */
    public function addStockOut($productId, $quantity, $notes = null)
    {
        $data = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'type'       => 'out',
            'notes'      => $notes,
            'date'       => date('Y-m-d H:i:s')
        ];

        return $this->insert($data);
    }

    /**
     * Get all products with stock information by shop
     */
    public function getProductsWithStockByShop($shopId)
    {
        $db = \Config\Database::connect();
        
        // Get all products for the shop
        $products = $db->table('products')
                      ->where('shop_id', $shopId)
                      ->orderBy('id', 'DESC')
                      ->get()
                      ->getResultArray();

        if (empty($products)) {
            return [];
        }

        // Get stock totals for all products in one query
        $productIds = array_column($products, 'id');
        $stockRows = $db->table('stocks')
                       ->select('product_id, SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) AS stock_total')
                       ->whereIn('product_id', $productIds)
                       ->groupBy('product_id')
                       ->get()
                       ->getResultArray();

        // Create stock mapping
        $stockMap = [];
        foreach ($stockRows as $r) {
            $stockMap[$r['product_id']] = (int) $r['stock_total'];
        }

        // Add stock information to each product
        foreach ($products as &$product) {
            $product['stock'] = $stockMap[$product['id']] ?? 0;
        }
        unset($product);

        return $products;
    }

    /**
     * Get paginated products with stock information by shop
     */
    public function getProductsWithStockByShopPaginated($shopId, $limit = 10, $offset = 0)
    {
        $db = \Config\Database::connect();
        
        // Get total count first
        $total = $db->table('products')
                   ->where('shop_id', $shopId)
                   ->countAllResults();

        if ($total == 0) {
            return [
                'data' => [],
                'total' => 0
            ];
        }

        // Get paginated products for the shop
        $products = $db->table('products')
                      ->where('shop_id', $shopId)
                      ->orderBy('id', 'DESC')
                      ->limit($limit, $offset)
                      ->get()
                      ->getResultArray();

        if (empty($products)) {
            return [
                'data' => [],
                'total' => $total
            ];
        }

        // Get stock totals for the paginated products
        $productIds = array_column($products, 'id');
        $stockRows = $db->table('stocks')
                       ->select('product_id, SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) AS stock_total')
                       ->whereIn('product_id', $productIds)
                       ->groupBy('product_id')
                       ->get()
                       ->getResultArray();

        // Create stock mapping
        $stockMap = [];
        foreach ($stockRows as $r) {
            $stockMap[$r['product_id']] = (int) $r['stock_total'];
        }

        // Add stock information to each product
        foreach ($products as &$product) {
            $product['stock'] = $stockMap[$product['id']] ?? 0;
        }
        unset($product);

        return [
            'data' => $products,
            'total' => $total
        ];
    }

    /**
     * Get paginated products with stock information by shop with filters
     */
    public function getProductsWithStockByShopFiltered($shopId, $filters = [], $limit = 20, $offset = 0)
    {
        $db = \Config\Database::connect();
        
        // Build the base query for counting
        $countQuery = $db->table('products p');
        $countQuery->where('p.shop_id', $shopId);
        
        // Build the base query for data
        $dataQuery = $db->table('products p');
        $dataQuery->where('p.shop_id', $shopId);
        
        // Apply filters to both queries
        $this->applyProductFilters($countQuery, $filters);
        $this->applyProductFilters($dataQuery, $filters);
        
        // Get total count first
        $total = $countQuery->countAllResults();

        if ($total == 0) {
            return [
                'data' => [],
                'total' => 0
            ];
        }

        // Get paginated products
        $products = $dataQuery->orderBy('p.id', 'DESC')
                             ->limit($limit, $offset)
                             ->get()
                             ->getResultArray();

        if (empty($products)) {
            return [
                'data' => [],
                'total' => $total
            ];
        }

        // Get stock totals for the paginated products
        $productIds = array_column($products, 'id');
        $stockQuery = $db->table('stocks s')
                        ->select('s.product_id, SUM(CASE WHEN s.type = "in" THEN s.quantity ELSE -s.quantity END) AS stock_total')
                        ->whereIn('s.product_id', $productIds);
        
        // Apply date range filter to stock if specified
        if (!empty($filters['date_start']) || !empty($filters['date_end'])) {
            if (!empty($filters['date_start'])) {
                $stockQuery->where('DATE(s.created_at) >=', $filters['date_start']);
            }
            if (!empty($filters['date_end'])) {
                $stockQuery->where('DATE(s.created_at) <=', $filters['date_end']);
            }
        }
        
        $stockRows = $stockQuery->groupBy('s.product_id')
                               ->get()
                               ->getResultArray();

        // Create stock mapping
        $stockMap = [];
        foreach ($stockRows as $r) {
            $stockMap[$r['product_id']] = (int) $r['stock_total'];
        }

        // Add stock information to each product
        foreach ($products as &$product) {
            $product['stock'] = $stockMap[$product['id']] ?? 0;
        }
        unset($product);

        return [
            'data' => $products,
            'total' => $total
        ];
    }

    /**
     * Apply filters to product query
     */
    private function applyProductFilters($query, $filters)
    {
        // Search by name (partial match, case insensitive)
        if (!empty($filters['search'])) {
            $query->like('p.name', $filters['search']);
        }
        
        // Filter by product type (product/composition)
        if (!empty($filters['product_type'])) {
            $query->where('p.type', $filters['product_type']);
        }
        
        // Filter by specific product name (exact match, case insensitive)
        if (!empty($filters['product_name'])) {
            $query->where('LOWER(p.name)', strtolower($filters['product_name']));
        }
        
        // Date range filter - filter products created within date range
        if (!empty($filters['date_start'])) {
            $query->where('DATE(p.created_at) >=', $filters['date_start']);
        }
        
        if (!empty($filters['date_end'])) {
            $query->where('DATE(p.created_at) <=', $filters['date_end']);
        }
    }

}
