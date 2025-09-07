<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductCompositionModel extends Model
{
    protected $table            = 'product_compositions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id',
        'composition_id',
        'quantity',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'             => 'integer',
        'product_id'     => 'integer',
        'composition_id' => 'integer',
        'quantity'       => 'integer',
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
        'product_id'     => 'required|integer',
        'composition_id' => 'required|integer',
        'quantity'       => 'required|integer|greater_than[0]',
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
     * Get compositions for a specific product
     */
    public function getCompositionsByProduct($productId)
    {
        return $this->select('product_compositions.*, compositions.name, compositions.cost_price, compositions.selling_price, compositions.unit')
                    ->join('compositions', 'compositions.id = product_compositions.composition_id')
                    ->where('product_compositions.product_id', $productId)
                    ->findAll();
    }

    /**
     * Delete all compositions for a product
     */
    public function deleteByProduct($productId)
    {
        return $this->where('product_id', $productId)->delete();
    }
}
