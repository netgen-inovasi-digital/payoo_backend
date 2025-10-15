<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
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
    protected $deletedField  = '';

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
     * Check if category is used by products before delete
     */
    protected function checkCategoryUsage(array $data)
    {
        if (isset($data['id'])) {
            $productModel = new \App\Models\ProductModel();
            $count = $productModel->where('category_id', $data['id'][0])->countAllResults();
            
            if ($count > 0) {
                throw new \Exception('Cannot delete category. It is being used by ' . $count . ' product(s).');
            }
        }
        return $data;
    }
}
