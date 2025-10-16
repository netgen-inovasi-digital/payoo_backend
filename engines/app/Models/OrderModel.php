<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'shop_id',
        'status',
        'notes',
        'total',
        'amount_paid',
        'change_money',
        'tax',
        'payment_method',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'           => 'integer',
        'user_id'      => 'integer',
        'shop_id'      => 'integer',
        'total'        => 'float',
        'amount_paid'  => 'float',
        'change_money' => 'float',
        'tax'          => 'float',
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
        'user_id'        => 'required|integer',
        'shop_id'        => 'required|integer',
        'status'         => 'required|string|max_length[20]',
        'notes'          => 'permit_empty|string',
        'total'          => 'required|decimal',
        'amount_paid'    => 'permit_empty|decimal',
        'change_money'   => 'permit_empty|decimal',
        'tax'            => 'permit_empty|decimal',
        'payment_method' => 'permit_empty|string|max_length[20]',
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
     * Restore a soft deleted order
     */
    public function restore($id)
    {
        return $this->update($id, [$this->deletedField => null]);
    }

    /**
     * Permanently delete an order
     */
    public function forceDelete($id)
    {
        // Delete related order items first
        $orderItemModel = new \App\Models\OrderItemModel();
        $orderItemModel->where('order_id', $id)->delete();
        
        return $this->where($this->primaryKey, $id)->purgeDeleted();
    }
}
