<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CompositionModel;

class Composition extends BaseController
{
    protected CompositionModel $model;

    public function __construct()
    {
        $this->model = new CompositionModel();
    }

    // GET /api/compositions
    public function index()
    {
        $items = $this->model->orderBy('id', 'DESC')->findAll();
        return api_respond_success($items, 'Composition list');
    }

    // GET /api/compositions/{id}
    public function show($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $item = $this->model->find($id);
        if (!$item) {
            return api_respond_not_found('Composition not found');
        }
        return api_respond_success($item, 'Composition detail');
    }

    // POST /api/compositions
    public function create()
    {
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $data = [
            'name'          => trim($json->name ?? ''),
            'cost_price'    => $json->cost_price ?? null,
            'selling_price' => $json->selling_price ?? null,
            'unit'          => $json->unit ?? null,
        ];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!$this->model->insert($data)) {
            return api_respond_server_error('Failed to create composition');
        }
        $created = $this->model->find($this->model->getInsertID());
        return api_respond_created($created, 'Composition created');
    }

    // PUT /api/compositions/{id}
    public function update($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $existing = $this->model->find($id);
        if (!$existing) {
            return api_respond_not_found('Composition not found');
        }
        $json = $this->request->getJSON();
        if (!$json) {
            return api_respond_error('Invalid JSON', 400);
        }
        $data = [
            'name'          => isset($json->name) ? trim($json->name) : $existing['name'],
            'cost_price'    => isset($json->cost_price) ? $json->cost_price : $existing['cost_price'],
            'selling_price' => isset($json->selling_price) ? $json->selling_price : $existing['selling_price'],
            'unit'          => isset($json->unit) ? $json->unit : $existing['unit'],
        ];
        if (!$this->model->validate($data)) {
            return api_respond_validation_error($this->model->errors());
        }
        if (!$this->model->update($id, $data)) {
            return api_respond_server_error('Failed to update composition');
        }
        $updated = $this->model->find($id);
        return api_respond_success($updated, 'Composition updated');
    }

    // DELETE /api/compositions/{id}
    public function delete($id = null)
    {
        if (!$this->isValidId($id)) {
            return api_respond_validation_error(['id' => 'Invalid id']);
        }
        $existing = $this->model->find($id);
        if (!$existing) {
            return api_respond_not_found('Composition not found');
        }
        if (!$this->model->delete($id)) {
            return api_respond_server_error('Failed to delete composition');
        }
        return api_respond_success(null, 'Composition deleted');
    }
}
