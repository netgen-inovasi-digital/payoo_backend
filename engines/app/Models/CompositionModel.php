<?php

namespace App\Models;

use CodeIgniter\Model;

class CompositionModel extends Model
{
    protected $table            = 'compositions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // migration tidak memiliki deleted_at
    protected $protectFields    = true;
    protected $allowedFields    = [
        'shop_id',
        'name',
        'cost_price',
        'selling_price',
        'unit',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id'            => 'integer',
        'shop_id'       => 'integer',
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
        'name'          => 'required|string|max_length[100]',
        'cost_price'    => 'required|decimal',
        'selling_price' => 'required|decimal',
        'unit'          => 'required|in_list[pcs,gr,lembar]',
    ];
    protected $validationMessages = [
        'unit' => [
            'in_list' => 'Unit must be one of: pcs, gr, lembar'
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
}
