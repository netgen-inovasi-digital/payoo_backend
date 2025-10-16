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
    protected $useSoftDeletes   = true;
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
    protected $deletedField  = 'deleted_at';

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
                c.name as category_name,
                COALESCE(stock_summary.stock_total, 0) AS stock
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT 
                    product_id,
                    SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS stock_total
                FROM stocks
                GROUP BY product_id
            ) stock_summary ON stock_summary.product_id = p.id
            WHERE p.shop_id = ? AND p.type = ? AND p.deleted_at IS NULL
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
     * Get products with category information (including soft deleted categories)
     */
    public function getProductsWithCategoryByShop($shopId, $type = 'product')
    {
        return $this->select('
                products.*, 
                categories.name as category_name,
                CASE 
                    WHEN categories.deleted_at IS NOT NULL THEN 1 
                    ELSE 0 
                END as category_is_deleted
            ')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->where('products.shop_id', $shopId)
            ->where('products.type', $type)
            ->orderBy('products.id', 'DESC')
            ->findAll();
    }

    /**
     * Get single product with stock and compositions (optimized)
     */
    public function getProductWithDetailsById($productId, $shopId)
    {
        $db = Database::connect();
        
        // First get the product with stock and category name (even if category is soft deleted)
        $product = $db->query("
            SELECT 
                p.*,
                c.name as category_name,
                COALESCE(stock_summary.stock_total, 0) AS stock
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT 
                    product_id,
                    SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS stock_total
                FROM stocks
                GROUP BY product_id
            ) stock_summary ON stock_summary.product_id = p.id
            WHERE p.shop_id = ? AND p.id = ? AND p.deleted_at IS NULL
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
            JOIN products c ON c.id = pc.composition_id AND c.type = 'composition' AND c.deleted_at IS NULL
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

    /**
     * Restore a soft deleted product
     */
    public function restore($id)
    {
        return $this->update($id, [$this->deletedField => null]);
    }

    /**
     * Permanently delete a product
     */
    public function forceDelete($id)
    {
        // Check if product is used in any orders
        $orderItemModel = new \App\Models\OrderItemModel();
        $count = $orderItemModel->where('product_id', $id)->countAllResults();
        
        if ($count > 0) {
            throw new \Exception('Cannot permanently delete product. It is used in ' . $count . ' order(s).');
        }

        // Check if product is used as composition in other products
        $compositionModel = new \App\Models\ProductCompositionModel();
        $count = $compositionModel->where('composition_id', $id)->countAllResults();
        
        if ($count > 0) {
            throw new \Exception('Cannot permanently delete product. It is used as composition in ' . $count . ' product(s).');
        }
        
        // Delete related stocks first
        $stockModel = new \App\Models\StockModel();
        $stockModel->where('product_id', $id)->delete();
        
        // Delete related product compositions
        $compositionModel->where('product_id', $id)->delete();
        
        return $this->where($this->primaryKey, $id)->purgeDeleted();
    }
}
