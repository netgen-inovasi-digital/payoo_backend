<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'shop_id',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id' => 'integer',
        'shop_id' => 'integer',
    ];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'shop_id' => 'required|integer',
        // Note: For update you should override rule to ignore current ID (see controller advice)
        'name' => 'required|string|max_length[100]'
    ];
    protected $validationMessages   = [];
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
    protected $beforeDelete   = ['checkCategoryUsage'];
    protected $afterDelete    = [];

    /**
     * Restore a soft deleted category
     */
    public function restore($id)
    {
        return $this->update($id, [$this->deletedField => null]);
    }

    /**
     * Permanently delete a category
     */
    public function forceDelete($id)
    {
        // Check if category has any products (including deleted ones)
        $productModel = new \App\Models\ProductModel();
        $count = $productModel->withDeleted()->where('category_id', $id)->countAllResults();
        
        if ($count > 0) {
            throw new \Exception('Cannot permanently delete category. It has ' . $count . ' product(s) associated with it.');
        }
        
        return $this->where($this->primaryKey, $id)->purgeDeleted();
    }

    /**
     * Safe delete with product handling
     * This method will set category_id to NULL for all products using this category
     */
    public function safeDelete($id)
    {
        // First, set category_id to NULL for all products using this category
        $productModel = new \App\Models\ProductModel();
        $productModel->where('category_id', $id)->set(['category_id' => null])->update();
        
        // Then proceed with normal delete (soft delete)
        return $this->delete($id);
    }

    /**
     * Get categories with product count (including soft deleted products)
     */
    public function getCategoriesWithProductCount($shopId)
    {
        $db = \Config\Database::connect();
        
        return $db->query("
            SELECT 
                c.*,
                COUNT(p.id) as total_products,
                COUNT(CASE WHEN p.deleted_at IS NULL THEN 1 END) as active_products,
                COUNT(CASE WHEN p.deleted_at IS NOT NULL THEN 1 END) as deleted_products
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            WHERE c.shop_id = ? AND c.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY c.id DESC
        ", [$shopId])->getResultArray();
    }

    /**
     * Check if category is used by active products before soft delete
     * We allow soft delete even if products exist, but warn about it
     */
    protected function checkCategoryUsage(array $data)
    {
        if (isset($data['id'])) {
            $productModel = new \App\Models\ProductModel();
            // Only check active products (not soft deleted)
            $count = $productModel->where('category_id', $data['id'][0])
                                  ->where('deleted_at', null)
                                  ->countAllResults();
            
            // Log warning but allow soft delete
            if ($count > 0) {
                log_message('info', "Category {$data['id'][0]} is being soft deleted but is still used by {$count} active product(s).");
            }
        }
        return $data;
    }
}
