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

        // Convert fields to proper types for consistency
        foreach ($products as &$product) {
            $product['id'] = (int) $product['id'];
            $product['shop_id'] = (int) $product['shop_id'];
            $product['category_id'] = $product['category_id'] ? (int) $product['category_id'] : null;
            $product['cost_price'] = (int) (float) $product['cost_price']; // Convert string decimal to int
            $product['selling_price'] = (int) (float) $product['selling_price']; // Convert string decimal to int
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
        
        // First get the product with stock
        $product = $db->query("
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
            WHERE p.shop_id = ? AND p.id = ?
        ", [$shopId, $productId])->getRowArray();
        
        if (!$product) {
            return null;
        }
        
        // Convert fields to proper types for consistency  
        $product['id'] = (int) $product['id'];
        $product['shop_id'] = (int) $product['shop_id'];
        $product['category_id'] = $product['category_id'] ? (int) $product['category_id'] : null;
        $product['cost_price'] = (int) $product['cost_price']; // Convert string decimal to int
        $product['selling_price'] = (int) $product['selling_price']; // Convert string decimal to int
        $product['stock'] = (int) $product['stock'];
        
        // Then get compositions in separate optimized query
        $compositions = $db->query("
            SELECT 
                pc.id,
                pc.product_id,
                pc.composition_id,
                pc.quantity,
                pc.created_at,
                pc.updated_at,
                c.name as composition_name,
                c.cost_price,
                c.selling_price,
                c.unit
            FROM product_compositions pc
            JOIN products c ON c.id = pc.composition_id AND c.type = 'composition'
            WHERE pc.product_id = ?
            ORDER BY pc.id
        ", [$productId])->getResultArray();
        
        // Convert composition fields to proper types
        foreach ($compositions as &$comp) {
            $comp['id'] = (int) $comp['id'];
            $comp['product_id'] = (int) $comp['product_id'];
            $comp['composition_id'] = (int) $comp['composition_id'];
            $comp['quantity'] = (int) $comp['quantity'];
            $comp['cost_price'] = (int) $comp['cost_price'];
            $comp['selling_price'] = (int) $comp['selling_price'];
        }
        unset($comp);
        
        $product['compositions'] = $compositions;
        
        return $product;
    }
}
