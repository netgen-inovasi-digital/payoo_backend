<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopModel extends Model
{
    protected $table            = 'shops';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'name',
        'email',
        'address',
        'type',
        'province',
        'city',
        'phone',
        'photo',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
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
        'user_id' => 'required|integer',
        'name'    => 'required|string|max_length[100]',
        'email'   => 'permit_empty|valid_email|max_length[100]',
        'address' => 'permit_empty|string',
        'type'    => 'permit_empty|in_list[mandiri,perusahaan]',
        'province' => 'permit_empty|string|max_length[100]',
        'city'    => 'permit_empty|string|max_length[100]',
        'phone'   => 'permit_empty|string|max_length[100]',
        'photo'   => 'permit_empty|string|max_length[255]',
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
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Restore a soft deleted shop
     */
    public function restore($id)
    {
        return $this->update($id, [$this->deletedField => null]);
    }

    /**
     * Permanently delete a shop (use with caution)
     */
    public function forceDelete($id)
    {
        // Check if shop has any orders
        $orderModel = new \App\Models\OrderModel();
        $count = $orderModel->withDeleted()->where('shop_id', $id)->countAllResults();
        
        if ($count > 0) {
            throw new \Exception('Cannot permanently delete shop. It has ' . $count . ' order(s) associated with it.');
        }

        // Check if shop has any products
        $productModel = new \App\Models\ProductModel();
        $count = $productModel->withDeleted()->where('shop_id', $id)->countAllResults();
        
        if ($count > 0) {
            throw new \Exception('Cannot permanently delete shop. It has ' . $count . ' product(s) associated with it.');
        }

        // Check if shop has any categories
        $categoryModel = new \App\Models\CategoryModel();
        $count = $categoryModel->withDeleted()->where('shop_id', $id)->countAllResults();
        
        if ($count > 0) {
            throw new \Exception('Cannot permanently delete shop. It has ' . $count . ' category(ies) associated with it.');
        }
        
        return $this->where($this->primaryKey, $id)->purgeDeleted();
    }
}
