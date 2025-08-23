<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\StockModel;
use App\Models\CompositionModel;

helper(['api_response_helper']);

class Stock extends BaseController
{
    protected StockModel $model;

    public function __construct()
    {
        $this->model = new StockModel();
    }

    // GET /api/stocks/{composition_id}
    public function show($compositionId = null)
    {
        if (!$this->isValidId($compositionId)) {
            return api_respond_validation_error(['composition_id' => 'Invalid composition id']);
        }
        $composition = (new CompositionModel())->find($compositionId);
        if (!$composition) {
            return api_respond_not_found('Composition not found');
        }
        $rows = $this->model
            ->where('composition_id', $compositionId)
            ->orderBy('date', 'DESC')
            ->findAll();
        return api_respond_success($rows, 'Stock records for composition');
    }

    // POST /api/stocks
    public function create()
    {
        $payload = $this->decodeToken();
        if (!$payload) {
            return api_respond_unauthorized('Invalid token');
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $data = [
            'composition_id' => (int) ($json->composition_id ?? 0),
            'quantity'       => isset($json->quantity) ? (int) $json->quantity : null,
            'type'           => $json->type ?? null,
            'date'           => $json->date ?? null,
        ];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!(new CompositionModel())->find($data['composition_id'])) {
            return api_respond_validation_error(['composition_id' => 'Composition not found']);
        }
        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create stock record');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Stock record created');
    }
}
