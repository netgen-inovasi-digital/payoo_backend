<?php

namespace App\Models;

use CodeIgniter\Model;

class CompositionModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
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
        'category_id'   => 'integer',
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
        'name'          => 'required|string|max_length[150]',
        'description'   => 'permit_empty|string',
        'category_id'   => 'permit_empty|integer',
        'photo'         => 'permit_empty|string|max_length[255]',
        'type'          => 'required|in_list[composition]',
        'unit'          => 'permit_empty|in_list[pcs,gr,kg,ml,liter,lembar,slice,butir,pack,botol]',
        'cost_price'    => 'required|decimal',
        'selling_price' => 'required|decimal',
    ];
    protected $validationMessages = [
        'unit' => [
            'in_list' => 'Unit must be one of: pcs, gr, kg, ml, liter, lembar, slice, butir, pack, botol'
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
     * Get all compositions for a specific shop
     */
    public function getCompositionsByShop($shopId)
    {
        return $this->where('shop_id', $shopId)
                   ->where('type', 'composition')
                   ->findAll();
    }

    /**
     * Override find to ensure we only get compositions
     */
    public function find($id = null)
    {
        $this->where('type', 'composition');
        return parent::find($id);
    }

    /**
     * Override findAll to ensure we only get compositions
     */
    public function findAll(?int $limit = null, int $offset = 0)
    {
        $this->where('type', 'composition');
        return parent::findAll($limit, $offset);
    }
}
