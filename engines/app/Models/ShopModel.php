<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopModel extends Model
{
    protected $table            = 'shops';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
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
    protected $deletedField  = '';

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
}
