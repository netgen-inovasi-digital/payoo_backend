<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

class ProductModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // migration tidak menyediakan deleted_at
    protected $protectFields    = true;
    protected $allowedFields    = [
        'shop_id',
        'category_id',
        'name',
        'description',
        'photo',
        'type',
        'unit',
        'cost_price',
        'selling_price',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'            => 'integer',
        'shop_id'       => 'integer',
        'category_id'   => '?integer',
        'cost_price'    => 'float',
        'selling_price' => 'float',
    ];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = '';

    // Validation
    protected $validationRules = [
        'shop_id'       => 'required|integer',
        'category_id'   => 'permit_empty|integer',
        'name'          => 'required|string|max_length[150]',
        'description'   => 'permit_empty|string',
        'photo'         => 'permit_empty|string|max_length[255]',
        'type'          => 'required|in_list[product,composition]',
        'unit'          => 'permit_empty|in_list[pcs,gr,lembar]',
        'cost_price'    => 'required|decimal',
        'selling_price' => 'required|decimal',
    ];
    protected $validationMessages = [];
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
     * Get products by shop
     */
    public function getProductsByShop($shopId, $type = 'product')
    {
        return $this->where('shop_id', $shopId)
                   ->where('type', $type)
                   ->orderBy('id', 'DESC')
                   ->findAll();
    }

    /**
     * Get products with stock information by shop (optimized)
     */
    public function getProductsWithStockByShop($shopId, $type = 'product')
    {
        $db = Database::connect();
        
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
            WHERE p.shop_id = ? AND p.type = ?
            ORDER BY p.id DESC
        ", [$shopId, $type])->getResultArray();

        // Convert stock to integer for consistency
        foreach ($products as &$product) {
            $product['stock'] = (int) $product['stock'];
        }
        unset($product);

        return $products;
    }

    /**
     * Get single product with stock and compositions (optimized)
     */
    public function getProductWithDetailsById($productId, $shopId)
    {
        $db = Database::connect();
        
        $result = $db->query("
            SELECT 
                p.*,
                COALESCE(stock_summary.stock_total, 0) AS stock,
                GROUP_CONCAT(
                    CASE 
                        WHEN pc.composition_id IS NOT NULL 
                        THEN CONCAT('{\"id\":', pc.composition_id, ',\"name\":\"', REPLACE(c.name, '\"', '\\\\\"'), '\",\"quantity\":', pc.quantity, '}')
                        ELSE NULL 
                    END
                    SEPARATOR ','
                ) AS compositions_json
            FROM products p
            LEFT JOIN (
                SELECT 
                    product_id,
                    SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS stock_total
                FROM stocks
                GROUP BY product_id
            ) stock_summary ON stock_summary.product_id = p.id
            LEFT JOIN product_compositions pc ON pc.product_id = p.id
            LEFT JOIN products c ON c.id = pc.composition_id AND c.type = 'composition'
            WHERE p.shop_id = ? AND p.id = ?
            GROUP BY p.id
        ", [$shopId, $productId])->getRowArray();
        
        if (!$result) {
            return null;
        }
        
        // Convert stock to integer
        $result['stock'] = (int) $result['stock'];
        
        // Parse compositions JSON
        $compositions = [];
        if (!empty($result['compositions_json'])) {
            $compositionItems = explode(',', $result['compositions_json']);
            foreach ($compositionItems as $item) {
                if (!empty($item)) {
                    $comp = json_decode($item, true);
                    if ($comp) {
                        $compositions[] = [
                            'composition_id' => (int) $comp['id'],
                            'name' => $comp['name'],
                            'quantity' => (int) $comp['quantity']
                        ];
                    }
                }
            }
        }
        
        // Remove the temporary JSON field and add parsed compositions
        unset($result['compositions_json']);
        $result['compositions'] = $compositions;
        
        return $result;
    }
}
