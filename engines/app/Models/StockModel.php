<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

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
                       ->where('products.shop_id', $shopId)
                       ->where('products.deleted_at IS NULL'); // Apply soft delete filter
        
        // Apply type filter if provided
        if ($typeFilter) {
            $builder->where('stocks.type', $typeFilter);
        }
        
        $results = $builder->orderBy('stocks.date', 'DESC')
                          ->findAll();
        
        // Convert fields to proper types for consistency
        foreach ($results as &$row) {
            $row['id'] = (int) $row['id'];
            $row['product_id'] = (int) $row['product_id'];
            $row['quantity'] = (int) $row['quantity'];
            $row['buy_price'] = $row['buy_price'] ? (int) $row['buy_price'] : null;
        }
        unset($row);
        
        return $results;
    }

    /**
     * Get paginated stock movements by shop with filters
     * Optimized: Single query with window function to get count and data together
     */
    public function getStockMovementsByShopPaginated($shopId, $filters = [], $limit = 20, $offset = 0)
    {
        $db = Database::connect();
        
        // Build WHERE conditions dynamically
        $whereConditions = ['p.shop_id = ?', 'p.deleted_at IS NULL'];
        $params = [$shopId];
        
        // Apply filters dynamically
        if (!empty($filters['type'])) {
            $whereConditions[] = 's.type = ?';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['product_id'])) {
            $whereConditions[] = 's.product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        
        if (!empty($filters['product_type'])) {
            $whereConditions[] = 'p.type = ?';
            $params[] = $filters['product_type'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = 'p.name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['date_start'])) {
            $whereConditions[] = 'DATE(s.date) >= ?';
            $params[] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $whereConditions[] = 'DATE(s.date) <= ?';
            $params[] = $filters['date_end'];
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Single optimized query with window function to get both count and data
        $query = "
            SELECT 
                s.*,
                p.name as product_name,
                p.type as product_type,
                COUNT(*) OVER() as total_count
            FROM stocks s
            INNER JOIN products p ON p.id = s.product_id
            {$whereClause}
            ORDER BY s.date DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $results = $db->query($query, $params)->getResultArray();
        
        // Extract total count from first row (if exists)
        $total = !empty($results) ? (int) $results[0]['total_count'] : 0;
        
        // Convert fields to proper types and remove total_count from each row
        foreach ($results as &$row) {
            // Convert fields to proper integer types
            $row['id'] = (int) $row['id'];
            $row['product_id'] = (int) $row['product_id'];
            $row['quantity'] = (int) $row['quantity'];
            $row['buy_price'] = $row['buy_price'] ? (int) $row['buy_price'] : null;
            
            // Remove the window function count field
            unset($row['total_count']);
        }
        unset($row);
        
        return [
            'data' => $results,
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
     * Optimized: Single query with LEFT JOIN to avoid N+1 problem
     */
    public function getProductsWithStockByShop($shopId)
    {
        $db = Database::connect();
        
        // Single optimized query with LEFT JOIN to get products and stock in one go
        // Added soft delete filter: p.deleted_at IS NULL
        $products = $db->query("
            SELECT 
                p.*,
                COALESCE(stock_summary.stock_total, 0) AS stock
            FROM products p
            LEFT JOIN (
                SELECT 
                    product_id,
                    SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS stock_total
                FROM stocks
                GROUP BY product_id
            ) stock_summary ON stock_summary.product_id = p.id
            WHERE p.shop_id = ? AND p.deleted_at IS NULL
            ORDER BY p.id DESC
        ", [$shopId])->getResultArray();

        // Convert fields to proper types for consistency
        foreach ($products as &$product) {
            $product['id'] = (int) $product['id'];
            $product['shop_id'] = (int) $product['shop_id'];
            $product['category_id'] = $product['category_id'] ? (int) $product['category_id'] : null;
            $product['cost_price'] = (int) $product['cost_price'];
            $product['selling_price'] = (int) $product['selling_price'];
            $product['stock'] = (int) $product['stock'];
        }
        unset($product);

        return $products;
    }

}
