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
     * Get compositions for a specific product (alias for backward compatibility)
     */
    public function getCompositionsByProduct($productId)
    {
        return $this->getProductCompositions($productId);
    }

    /**
     * Get product compositions with details
     */
    public function getProductCompositions($productId)
    {
        return $this->select('product_compositions.*, products.name as composition_name, products.cost_price, products.selling_price, products.unit')
                    ->join('products', 'products.id = product_compositions.composition_id')
                    ->where('product_compositions.product_id', $productId)
                    ->where('products.type', 'composition')
                    ->findAll();
    }

    /**
     * Get products that use a specific composition
     */
    public function getProductsUsingComposition($compositionId)
    {
        return $this->select('product_compositions.*, products.name as product_name, products.selling_price')
                    ->join('products', 'products.id = product_compositions.product_id')
                    ->where('product_compositions.composition_id', $compositionId)
                    ->where('products.type', 'product')
                    ->findAll();
    }

    /**
     * Calculate total cost for a product based on its compositions
     */
    public function calculateProductCost($productId)
    {
        $compositions = $this->getProductCompositions($productId);
        $totalCost = 0;

        foreach ($compositions as $composition) {
            $totalCost += $composition['cost_price'] * $composition['quantity'];
        }

        return $totalCost;
    }

    /**
     * Update product compositions (delete old and insert new)
     */
    public function updateProductCompositions($productId, $compositions)
    {
        // Start transaction
        $this->db->transStart();

        // Delete existing compositions
        $this->deleteByProduct($productId);

        // Insert new compositions
        foreach ($compositions as $composition) {
            $data = [
                'product_id' => $productId,
                'composition_id' => $composition['composition_id'],
                'quantity' => $composition['quantity']
            ];
            $this->insert($data);
        }

        // Complete transaction
        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Check if composition is used by any product
     */
    public function isCompositionUsed($compositionId)
    {
        return $this->where('composition_id', $compositionId)->countAllResults() > 0;
    }

    /**
     * Delete all compositions for a product
     */
    public function deleteByProduct($productId)
    {
        return $this->where('product_id', $productId)->delete();
    }
}
