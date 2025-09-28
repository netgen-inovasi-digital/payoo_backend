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
    public function getStockMovementsByShop($shopId)
    {
        return $this->select('stocks.*, products.name as product_name')
                   ->join('products', 'products.id = stocks.product_id')
                   ->where('products.shop_id', $shopId)
                   ->orderBy('stocks.date', 'DESC')
                   ->findAll();
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

}
